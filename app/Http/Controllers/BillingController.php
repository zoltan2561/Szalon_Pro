<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\FiscalDocument;
use App\Models\Salon;
use App\Services\BillingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    public function index(): View
    {
        $this->enabled();

        return view('admin.billing', ['documents' => FiscalDocument::orderByDesc('id')->paginate(25), 'keyConfigured' => filled(Salon::current()->billing_agent_key)]);
    }

    public function settings(Request $request): RedirectResponse
    {
        $this->enabled();
        $data = $request->validate(['billing_agent_key' => 'nullable|string|max:200', 'billing_vat' => ['required', Rule::in(['27', '18', '5', '0', 'AAM', 'TAM'])], 'billing_receipt_prefix' => 'nullable|regex:/^[A-Z0-9]{1,10}$/', 'accountant_approved' => 'accepted', 'duplicate_guard' => 'accepted', 'billing_sending_enabled' => 'sometimes|boolean']);
        if (empty($data['billing_agent_key'])) {
            unset($data['billing_agent_key']);
        }
        unset($data['accountant_approved'], $data['duplicate_guard']);
        $data['billing_sending_enabled'] = $request->boolean('billing_sending_enabled');
        $data['billing_approved_at'] = now();
        Salon::current()->update($data);

        return back()->with('success', 'A bizonylatkezelés beállításait elmentettük.');
    }

    public function issue(Booking $booking, Request $request, BillingService $billing): RedirectResponse
    {
        $this->enabled();
        $data = $request->validate(['kind' => ['required', Rule::in(['invoice', 'receipt'])], 'payment_method' => ['required', Rule::in(['készpénz', 'bankkártya', 'átutalás', 'ajándékutalvány', 'vegyes'])], 'buyer_name' => 'required_if:kind,invoice|nullable|string|max:120', 'buyer_postcode' => 'required_if:kind,invoice|nullable|string|max:20', 'buyer_city' => 'required_if:kind,invoice|nullable|string|max:100', 'buyer_address' => 'required_if:kind,invoice|nullable|string|max:200', 'buyer_email' => 'nullable|email|max:200', 'buyer_tax_number' => 'nullable|regex:/^[0-9]{8}-[1-5]-[0-9]{2}$/', 'confirm' => 'accepted']);
        $document = $billing->issue($booking, $data, $request->user());

        return back()->with($document->status === 'issued' ? 'success' : 'error', $document->status === 'issued' ? 'A bizonylat elkészült: '.$document->document_number : 'A kiállítás nem igazolt. Állapot: '.$document->status.'. Ellenőrizd a bizonylatnaplót.');
    }

    public function reconcile(FiscalDocument $document, Request $request, BillingService $billing): RedirectResponse
    {
        $this->enabled();
        $document = $billing->reconcile($document, $request->user());

        return back()->with($document->status === 'issued' ? 'success' : 'error', $document->status === 'issued' ? 'A szolgáltatói egyeztetés sikeres.' : 'A szolgáltatói eredmény továbbra sem igazolt. Új bizonylat nem indult.');
    }

    public function pdf(FiscalDocument $document): Response
    {
        $this->enabled();
        abort_unless($document->status === 'issued' && $document->pdf, 404);

        return response(base64_decode($document->pdf, true), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="bizonylat-'.$document->id.'.pdf"', 'Cache-Control' => 'no-store, private']);
    }

    private function enabled(): void
    {
        abort_unless(Salon::current()->featureEnabled('billing'), 404);
    }
}
