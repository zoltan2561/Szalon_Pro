<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ValidationTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_controller_validation_fields_have_hungarian_attribute_names(): void
    {
        $attributes = trans('validation.attributes', [], 'hu');
        $missing = [];
        $checked = [];
        foreach (glob(app_path('Http/Controllers/*.php')) as $path) {
            $source = file_get_contents($path);
            preg_match_all('/->validate\s*\(\s*(\[)/', $source, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[1] as [$opening, $offset]) {
                $depth = 0;
                $previous = null;
                foreach (token_get_all('<?php '.substr($source, $offset)) as $token) {
                    if ($token === '[') {
                        $depth++;
                    } elseif ($token === ']') {
                        $depth--;
                        if ($depth === 0) {
                            break;
                        }
                    } elseif ($depth === 1 && is_array($token) && $token[0] === T_DOUBLE_ARROW && is_array($previous) && $previous[0] === T_CONSTANT_ENCAPSED_STRING) {
                        $field = substr($previous[1], 1, -1);
                        $checked[$field] = true;
                        if (! str_starts_with($field, 'appearance.') && ! array_key_exists($field, $attributes)) {
                            $missing[$field] = basename($path);
                        }
                    }
                    if (! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        $previous = $token;
                    }
                }
            }
        }
        $this->assertArrayHasKey('amount', $checked);
        $this->assertArrayHasKey('appearance.ink', $checked);
        $this->assertSame([], $missing, json_encode($missing, JSON_UNESCAPED_UNICODE));
    }

    public function test_negative_voucher_amount_names_the_field_in_hungarian(): void
    {
        Salon::factory()->create(['features' => ['vouchers' => true]]);
        $owner = User::factory()->create(['role' => 'owner']);
        $response = $this->actingAs($owner)->postJson(route('admin.vouchers.store'), [
            'buyer_name' => 'Minta vásárló', 'amount' => -500, 'expires_at' => now()->addYear()->toDateString(), 'request_key' => Str::uuid()->toString(),
        ]);
        $response->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->assertSame('A(z) összeg minimuma 1.', $response->json('errors.amount.0'));
        $this->assertDatabaseCount('gift_vouchers', 0);
    }
}
