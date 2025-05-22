<?php
namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\Controller;

class AuthController extends Controller
{
    public function __construct()
    {
        helper(['cookie', 'url']);
    }

    public function register()
    {
        return view('auth/register');
    }

    public function store()
    {
        $userModel = new UserModel();

        $data = [
            'name' => $this->request->getPost('name'),
            'email' => $this->request->getPost('email'),
            'password' => password_hash($this->request->getPost('password'), PASSWORD_DEFAULT),
            'role' => 'peserta' // Force role to be 'peserta' for security
        ];

        $userModel->insert($data);
        return redirect()->to('/login')->with('message', 'Registration successful! Please login.');
    }

    public function login()
    {
        return view('auth/login');
    }

    public function auth()
    {
        $userModel = new UserModel();
        $email = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        $user = $userModel->where('email', $email)->first();

        if ($user && password_verify($password, $user['password'])) {
            // Destroy any existing session
            if (session()->isStarted) {
                session()->destroy();
            }

            // Start a new session
            session()->start();

            // Set session data
            $isAdmin = ($user['role'] === 'admin');
            $sessionData = [
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'user_email' => $user['email'],
                'user_role' => $user['role'],
                'is_admin' => $isAdmin,
                'logged_in' => true,
                'last_activity' => time()
            ];
            
            session()->set($sessionData);

            // Regenerate session ID for security
            session()->regenerate(true);

            // Set remember-me cookie if requested
            if ($this->request->getPost('remember') == '1') {
                $this->response->setCookie('remember_token', $user['id'], 30 * 24 * 60 * 60); // 30 days
            }

            // Redirect based on role
            if ($isAdmin) {
                return redirect()->to('/admin/dashboard');
            } else {
                return redirect()->to('/peserta/dashboard');
            }
        }

        return redirect()->to('/login')->with('error', 'Email atau password salah');
    }

    public function logout()
    {
        // Clear the remember-me cookie if it exists
        $rememberToken = get_cookie('remember_token');
        if ($rememberToken) {
            delete_cookie('remember_token');
        }

        // Destroy the session
        session()->destroy();
        
        return redirect()->to('/login')->with('message', 'Anda telah berhasil logout');
    }
}
