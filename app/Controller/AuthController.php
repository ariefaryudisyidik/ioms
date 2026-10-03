<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
use App\Repository\MySqlLoginAttemptRepository;
use App\Repository\MySqlUserRepository;
use App\Service\AuthService;
use App\Service\LoginThrottle;

final class AuthController extends Controller
{
    private const LOGIN_URL = '/login';

    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');

            return;
        }

        $this->render('auth.login', [
            'old' => ['email' => Session::old('email', '')],
        ]);
    }

    public function login(Request $request): void
    {
        $this->handle(function () use ($request) {
            $email = trim((string) $request->input('email', ''));
            $password = (string) $request->input('password', '');

            $ip = (string) $request->server('REMOTE_ADDR', '');
            $throttle = new LoginThrottle(new MySqlLoginAttemptRepository($this->pdo()));
            if ($throttle->isLocked($email, $ip)) {
                $this->rejectLogin($email, 'Too many failed sign-in attempts. Please try again in a few minutes.');

                return;
            }

            $service = new AuthService(new MySqlUserRepository($this->pdo()));
            $user = $service->attempt($email, $password);

            if ($user === null) {
                $throttle->recordFailure($email, $ip);
                $this->rejectLogin($email, 'Invalid credentials or inactive account.');

                return;
            }
            $throttle->reset($email, $ip);

            Auth::login([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ]);
            Session::clearOldInput();
            $this->redirect('/dashboard');
        }, false, self::LOGIN_URL);
    }

    private function rejectLogin(string $email, string $message): void
    {
        Session::setOldInput(['email' => $email]);
        Session::setErrors(['email' => $message]);
        $this->redirect(self::LOGIN_URL);
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect(self::LOGIN_URL);
    }
}
