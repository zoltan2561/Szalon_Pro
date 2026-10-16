<?php

namespace Tests\Feature;

use App\Models\Salon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_requires_explicit_publication(): void
    {
        Salon::factory()->create(['published' => false]);
        $this->get('/')->assertNotFound();
        $this->get('/admin')->assertRedirect('/belepes');
    }
}
