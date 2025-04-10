<?php

namespace PRMSU\Models;

class User
{
    public $user_id;
    public $username;
    public $password_hash;
    public $email;
    public $first_name;
    public $last_name;
    public $role_id;
    public $role_name;
    public $department_id;
    public $college_id;
    public $faculty_id;
    public $is_active;
    public $created_at;
    public $updated_at;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function getFullName(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function isAdmin(): bool
    {
        return $this->role_name === 'Admin';
    }

    public function isVPAA(): bool
    {
        return $this->role_name === 'VPAA';
    }

    public function isChair(): bool
    {
        return $this->role_name === 'Chair';
    }

    public function isDean(): bool
    {
        return $this->role_name === 'Dean';
    }

    public function isFaculty(): bool
    {
        return $this->role_name === 'Faculty';
    }

    public function isDI(): bool
    {
        return $this->role_name === 'D.I';
    }
}
