<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
use App\Repository\MySqlUserRepository;
use App\Service\AuthService;

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

            $service = new AuthService(new MySqlUserRepository($this->pdo()));
            $user = $service->attempt($email, $password);

            if ($user === null) {
                Session::setOldInput(['email' => $email]);
                Session::setErrors(['email' => 'Invalid credentials or inactive account.']);
                $this->redirect(self::LOGIN_URL);

                return;
            }

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

    public function logout(): void
    {
        Auth::logout();
        $this->redirect(self::LOGIN_URL);
    }
}
