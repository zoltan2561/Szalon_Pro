<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\PasswordChanged;
use App\Services\OperationsHealth;
use App\Services\SendPasswordResetLink;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

class PasswordResetController extends Controller
{
    public const RequestedMessage = 'Ha ehhez az e-mail-címhez tartozik fiók, hamarosan elküldjük a jelszó-visszaállító hivatkozást. Ellenőrizd a levélszemét mappát is.';

    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|string|email|max:200']);
        SendPasswordResetLink::dispatch(trim($data['email']));

        return redirect()->route('password.request')->with('success', self::RequestedMessage);
    }

    public function edit(Request $request, string $token): Response
    {
        $email = $request->query('email');
        $email = is_string($email) && strlen($email) <= 200 ? $email : '';
        $user = $this->findUser($email);
        $valid = $user && ! $user->isDemoViewer() && Password::broker()->tokenExists($user, $token);

        return response()->view('auth.reset-password', ['email' => $user?->email ?? $email, 'token' => $token, 'valid' => (bool) $valid])->header('Cache-Control', 'no-store, private');
    }

    public function update(Request $request, OperationsHealth $health): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|string|email|max:200', 'token' => 'required|string|max:200', 'password' => 'required|string|min:12|max:200'.($request->filled('password_confirmation') ? '|confirmed' : ''), 'password_confirmation' => 'required|string|max:200'], ['password_confirmation.required' => 'Add meg újra az új jelszót.']);
        $user = $this->findUser($data['email']);
        if ($user?->isDemoViewer()) {
            return redirect()->route('password.request')->withErrors(['email' => 'Ehhez a fiókhoz nem állítható vissza jelszó.']);
        }
        $data['email'] = $user?->email ?? $data['email'];
        $changedUser = null;
        $status = DB::transaction(function () use ($data, &$changedUser): string {
            DB::table(config('auth.passwords.users.table'))->where('email', $data['email'])->lockForUpdate()->first();

            return Password::broker()->reset($data, function (User $user, string $password) use (&$changedUser): void {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
                $changedUser = $user;
                event(new PasswordReset($user));
            });
        }, 5);
        if ($status !== Password::PASSWORD_RESET) {
            return redirect()->route('password.request')->withErrors(['email' => 'A hivatkozás lejárt vagy már felhasználtad. Kérj új jelszó-visszaállító hivatkozást.']);
        }
        if ($changedUser && $this->canSend($health)) {
            $this->notifyChange($changedUser, new PasswordChanged);
        }
        if (! $request->user() || $request->user()->id === $changedUser?->id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login')->with('success', 'A jelszavadat módosítottuk. Jelentkezz be az új jelszóval.');
    }

    private function findUser(string $email): ?User
    {
        return User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])->first();
    }

    private function canSend(OperationsHealth $health): bool
    {
        $transport = config('mail.mailers.'.config('mail.default').'.transport');

        return $health->mailReady() || (app()->environment(['local', 'testing']) && in_array($transport, ['array', 'smtp'], true));
    }

    private function notifyChange(User $user, Notification $notification): void
    {
        try {
            $user->notify($notification);
        } catch (Throwable) {
            Log::warning('A jelszóváltozás értesítő levele nem jutott el a levélküldőhöz.', ['user_id' => $user->id]);
        }
    }
}
