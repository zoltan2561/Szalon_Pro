<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\FiscalDocument;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BillingService
{
    public function configured(): bool
    {
        $salon = Salon::current();

        return $salon->featureEnabled('billing') && $salon->billing_sending_enabled && filled($salon->billing_agent_key) && filled($salon->billing_vat) && $salon->billing_approved_at !== null;
    }

    /** @param array<string, mixed> $data */
    public function issue(Booking $booking, array $data, User $actor): FiscalDocument
    {
        abort_unless($actor->role === 'owner' && Salon::current()->featureEnabled('billing'), 403);
        if (! $this->configured()) {
            $this->error('A bizonylatküldés nincs engedélyezve vagy a beállítások hiányosak.');
        }
        [$document, $send] = DB::transaction(function () use ($booking, $data, $actor): array {
            Salon::whereKey(1)->increment('lock_version');
            $booking = Booking::findOrFail($booking->id);
            if ($booking->status !== 'completed') {
                $this->error('Csak elvégzett foglalásból állítható ki bizonylat.');
            }
            if ($booking->isAutomaticallyCompleted()) {
                $this->error('Bizonylat kiállítása előtt pontosítsd az automatikusan lezárt látogatást.');
            }
            $document = FiscalDocument::where('booking_id', $booking->id)->lockForUpdate()->first();
            if ($document && $document->kind !== $data['kind']) {
                $this->error('Ehhez a foglaláshoz már más típusú bizonylatkérés tartozik.');
            }
            if ($document?->status === 'issued') {
                return [$document, false];
            }
            if ($document && in_array($document->status, ['sending', 'uncertain'], true)) {
                $this->error('A korábbi kiállítás eredménye bizonytalan vagy folyamatban van. Előbb egyeztess a szolgáltatónál; új kiállítás nem indult.');
            }
            $salon = Salon::current();
            if ($data['kind'] === 'receipt' && ! filled($salon->billing_receipt_prefix)) {
                $this->error('A nyugtaelőtag nincs beállítva.');
            }
            $gross = $booking->actual_amount ?? $booking->price;
            $rate = is_numeric($salon->billing_vat) ? (float) $salon->billing_vat : 0;
            $net = round($gross / (1 + $rate / 100), 2);
            $payload = array_intersect_key($data, array_flip(['buyer_name', 'buyer_postcode', 'buyer_city', 'buyer_address', 'buyer_email', 'buyer_tax_number', 'payment_method']));
            $payload += ['service_name' => $booking->service_name, 'gross' => $gross, 'net' => number_format($net, 2, '.', ''), 'tax' => number_format($gross - $net, 2, '.', ''), 'vat' => $salon->billing_vat, 'prefix' => $salon->billing_receipt_prefix, 'issue_date' => now($salon->timezone)->toDateString(), 'performance_date' => $booking->ends_at->setTimezone($salon->timezone)->toDateString()];
            if (! $document) {
                $document = FiscalDocument::create(['booking_id' => $booking->id, 'created_by_user_id' => $actor->id, 'kind' => $data['kind'], 'external_id' => 'sp-'.Str::uuid(), 'payload' => $payload]);
            }
            $document->update(['status' => 'sending', 'payload' => $payload, 'attempts' => $document->attempts + 1, 'last_attempt_at' => now(), 'error_code' => null]);

            return [$document, true];
        }, 5);
        if ($send) {
            $this->finish($document, app(SzamlazzHuClient::class)->issue($document));
        }

        return $document->fresh();
    }

    public function reconcile(FiscalDocument $document, User $actor): FiscalDocument
    {
        abort_unless($actor->role === 'owner' && $this->configured(), 403);
        $document->refresh();
        if ($document->status === 'sending' && $document->last_attempt_at?->gt(now()->subMinutes(5))) {
            $this->error('A korábbi kiállítás még folyamatban lehet. Kérjük, várj öt percet az egyeztetés előtt.');
        }
        $result = app(SzamlazzHuClient::class)->query($document);
        if ($result['status'] === 'issued' && (! $document->document_number || $document->document_number === $result['number'])) {
            $this->finish($document, $result);
        } elseif ($document->status !== 'issued') {
            $document->update(['status' => 'uncertain', 'error_code' => 'reconciliation_required']);
        }

        return $document->fresh();
    }

    /** @param array{status: string, number: ?string, pdf: ?string, error: ?string} $result */
    private function finish(FiscalDocument $document, array $result): void
    {
        $pdf = $result['pdf'] ?? ($document->status === 'issued' ? $document->pdf : null);
        $document->update(['status' => $result['status'], 'document_number' => $result['number'], 'pdf' => $pdf, 'pdf_link' => $pdf ? route('admin.billing.pdf', $document, false) : null, 'error_code' => $result['error'], 'issued_at' => $result['status'] === 'issued' ? ($document->issued_at ?? now()) : null]);
    }

    private function error(string $message): never
    {
        throw ValidationException::withMessages(['billing' => $message]);
    }
}
