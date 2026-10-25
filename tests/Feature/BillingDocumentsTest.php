<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\User;
use App\Services\BillingService;
use App\Services\SzamlazzHuClient;
use DOMDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillingDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Salon::factory()->create(['features' => ['billing' => true], 'billing_agent_key' => 'fake-agent-key', 'billing_vat' => 'AAM', 'billing_receipt_prefix' => 'NY', 'billing_sending_enabled' => true, 'billing_approved_at' => now()]);
        Http::preventStrayRequests();
    }

    public function test_invoice_is_issued_once_outside_transaction_and_pdf_is_owner_only(): void
    {
        $booking = Booking::factory()->create(['status' => 'completed']);
        $owner = User::factory()->create(['role' => 'owner']);
        $level = DB::transactionLevel();
        Http::fake(function ($request) use ($level) {
            $this->assertSame($level, DB::transactionLevel());
            $this->assertStringContainsString('action-xmlagentxmlfile', $request->body());

            return Http::response($this->success('TEST-1'), 200);
        });
        $billing = app(BillingService::class);
        $document = $billing->issue($booking, $this->invoiceData(), $owner);
        $this->assertSame('issued', $document->status);
        $this->assertSame('TEST-1', $document->document_number);
        $this->assertSame($document->id, $billing->issue($booking, $this->invoiceData(), $owner)->id);
        Http::assertSentCount(1);
        $this->assertStringNotContainsString('fake-agent-key', DB::table('salons')->value('billing_agent_key'));
        $this->actingAs($owner)->get(route('admin.billing.pdf', $document))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('admin.billing.index'))->assertOk()->assertDontSee('fake-agent-key');
        $staff = User::factory()->create(['role' => 'staff', 'staff_id' => $booking->staff_id]);
        $this->actingAs($staff)->get(route('admin.billing.pdf', $document))->assertForbidden();
    }

    public function test_uncertain_issue_is_not_replayed_and_can_be_reconciled_read_only(): void
    {
        $booking = Booking::factory()->create(['status' => 'completed']);
        $owner = User::factory()->create(['role' => 'owner']);
        Http::fake(function ($request) {
            if (str_contains($request->body(), 'action-xmlagentxmlfile')) {
                throw new ConnectionException('simulated timeout');
            }
            $this->assertStringContainsString('action-szamla_agent_pdf', $request->body());

            return Http::response($this->success('TEST-RECOVERED'), 200);
        });
        $document = app(BillingService::class)->issue($booking, $this->invoiceData(), $owner);
        $this->assertSame('uncertain', $document->status);
        $this->actingAs($owner)->postJson(route('admin.billing.issue', $booking), $this->invoiceData() + ['confirm' => 1])->assertUnprocessable();
        $recovered = app(BillingService::class)->reconcile($document, $owner);
        $this->assertSame('issued', $recovered->status);
        $this->assertSame('TEST-RECOVERED', $recovered->document_number);
        $this->assertDatabaseCount('fiscal_documents', 1);
    }

    public function test_generated_invoice_and_receipt_validate_against_official_schemas(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        Http::fake(fn ($request) => Http::response(str_contains($request->body(), 'action-xmlagentxmlfile') ? $this->success('TEST-2') : '<xmlnyugtavalasz><sikeres>true</sikeres><nyugta><alap><nyugtaszam>NY-1</nyugtaszam></alap></nyugta><nyugtaPdf>'.base64_encode('%PDF-1.4 test').'</nyugtaPdf></xmlnyugtavalasz>', 200));
        foreach (['invoice', 'receipt'] as $kind) {
            if ($kind === 'receipt') {
                Salon::current()->update(['billing_vat' => '27']);
            }
            $document = app(BillingService::class)->issue(Booking::factory()->create(['status' => 'completed']), array_replace($this->invoiceData(), ['kind' => $kind]), $owner);
            $xml = new DOMDocument;
            $this->assertTrue($xml->loadXML(app(SzamlazzHuClient::class)->issueXml($document)));
            $this->assertTrue($xml->schemaValidate(resource_path('schemas/szamlazz-'.$kind.'.xsd')));
            $this->assertSame('issued', $document->status);
        }
    }

    public function test_definitive_failure_is_retryable_but_disabled_configuration_sends_nothing(): void
    {
        $booking = Booking::factory()->create(['status' => 'completed']);
        $owner = User::factory()->create(['role' => 'owner']);
        Http::fakeSequence()->push('<xmlszamlavalasz><sikeres>false</sikeres><hibakod>3</hibakod></xmlszamlavalasz>', 200)->push($this->success('TEST-RETRY'), 200);
        $document = app(BillingService::class)->issue($booking, $this->invoiceData(), $owner);
        $this->assertSame('failed', $document->status);
        $this->assertSame($document->id, app(BillingService::class)->issue($booking, $this->invoiceData(), $owner)->id);
        $this->assertSame('issued', $document->fresh()->status);
        Http::assertSentCount(2);
        Salon::current()->update(['billing_sending_enabled' => false]);
        $this->actingAs($owner)->postJson(route('admin.billing.issue', Booking::factory()->create(['status' => 'completed'])), $this->invoiceData() + ['confirm' => 1])->assertUnprocessable();
        Http::assertSentCount(2);
    }

    /** @return array<string,string> */
    private function invoiceData(): array
    {
        return ['kind' => 'invoice', 'buyer_name' => 'Minta vevő', 'buyer_postcode' => '1111', 'buyer_city' => 'Budapest', 'buyer_address' => 'Minta utca 1.', 'payment_method' => 'készpénz'];
    }

    private function success(string $number): string
    {
        return '<xmlszamlavalasz><sikeres>true</sikeres><szamlaszam>'.$number.'</szamlaszam><pdf>'.base64_encode('%PDF-1.4 test').'</pdf></xmlszamlavalasz>';
    }
}
