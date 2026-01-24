<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';
    protected $primaryKey = 'id_audit_log';

    protected $fillable = [
        'id_user',
        'id_banda',
        'event',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'message'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function banda()
    {
        return $this->belongsTo(Banda::class, 'id_banda');
    }

    public function auditable()
    {
        return $this->morphTo();
    }
}
