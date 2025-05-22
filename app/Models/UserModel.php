<?php
namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $allowedFields = ['name', 'email', 'password', 'role'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = '';

    protected $beforeInsert = ['setDefaultRole'];
    
    protected function setDefaultRole(array $data)
    {
        if (!isset($data['data']['role'])) {
            $data['data']['role'] = 'peserta';
        }
        return $data;
    }
}
