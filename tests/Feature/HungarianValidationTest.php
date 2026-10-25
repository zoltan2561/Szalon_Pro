<?php

namespace Tests\Feature;

use App\Models\Salon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class HungarianValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_sms_consent_returns_hungarian_message_without_translation_keys(): void
    {
        Salon::factory()->create();
        $response = $this->postJson(route('booking.store'), ['sms_opt_in' => 'hibás']);
        $response->assertUnprocessable()->assertJsonValidationErrors('sms_opt_in');
        $this->assertStringContainsString('igen vagy nem', $response->json('errors.sms_opt_in.0'));
        $this->assertStringNotContainsString('validation.', $response->getContent());
    }

    public function test_missing_waitlist_identity_has_localized_required_without_message(): void
    {
        $validator = Validator::make([], ['name' => 'required_without:guest_id']);
        $this->assertTrue($validator->fails());
        $this->assertStringNotContainsString('validation.', $validator->errors()->first('name'));
    }
}
