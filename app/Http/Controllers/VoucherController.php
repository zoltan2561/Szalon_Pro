<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\GiftVoucher;
use App\Models\Salon;
use App\Models\Service;
use App\Models\VoucherRedemption;
use App\Services\BookingLinks;
use App\Services\VoucherService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class VoucherController extends Controller
{
    public function index(): View
    {
        $this->enabled();

        return view('admin.vouchers', ['vouchers' => GiftVoucher::orderByDesc('id')->paginate(20), 'services' => Service::where('active', true)->get()]);
    }

    public function store(Request $request, VoucherService $vouchers): RedirectResponse
    {
        $this->enabled();
        $data = $request->validate(['buyer_name' => 'required|string|max:120', 'service_id' => 'nullable|integer|exists:services,id', 'amount' => 'nullable|required_without:service_id|integer|min:1|max:10000000', 'expires_at' => 'required|date_format:Y-m-d|after:today', 'request_key' => 'required|uuid']);
        $data['service_id'] = empty($data['service_id']) ? null : (int) $data['service_id'];
        $data['expires_at'] = CarbonImmutable::parse($data['expires_at'], Salon::current()->timezone)->endOfDay()->utc()->toDateTimeString();
        $vouchers->issue($data, $request->user());

        return back()->with('success', 'Az utalványt kiállítottuk. A pénz átvételét ez nem igazolja.');
    }

    public function show(string $token): View
    {
        $this->enabled();
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $token), 404);
        $voucher = GiftVoucher::where('public_token_hash', hash('sha256', $token))->firstOrFail();

        return view('public.voucher', compact('voucher', 'token'));
    }

    public function qr(GiftVoucher $voucher, BookingLinks $links): Response
    {
        $this->enabled();

        return response($links->qr(route('vouchers.show', $voucher->public_token)), 200, ['Content-Type' => 'image/svg+xml', 'Content-Disposition' => 'attachment; filename="ajandek-qr.svg"', 'Cache-Control' => 'private, no-store']);
    }

    public function redeem(Booking $booking, Request $request, VoucherService $vouchers): RedirectResponse
    {
        $this->enabled();
        $data = $request->validate(['code' => 'required|string|max:24', 'amount' => 'required|integer|min:1|max:10000000', 'request_key' => 'required|uuid']);
        $voucher = GiftVoucher::where('code', strtoupper(trim($data['code'])))->firstOrFail();
        $vouchers->redeem($voucher, $booking, (int) $data['amount'], $data['request_key'], $request->user());

        return back()->with('success', 'Az utalványbeváltást rögzítettük.');
    }

    public function reverse(VoucherRedemption $redemption, Request $request, VoucherService $vouchers): RedirectResponse
    {
        $this->enabled();
        $data = $request->validate(['reason' => 'required|string|max:255', 'confirm' => 'accepted']);
        $vouchers->reverse($redemption, $data['reason'], $request->user());

        return back()->with('success', 'A beváltást sztornóztuk, az egyenleg helyreállt.');
    }

    private function enabled(): void
    {
        abort_unless(Salon::current()->featureEnabled('vouchers'), 404);
    }
}
