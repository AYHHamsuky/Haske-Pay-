<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Inertia\Inertia;

class AuditController extends Controller
{
    public function __invoke()
    {
        return Inertia::render('Admin/Audit', [
            'logs' => AuditLog::with('actor:id,name,phone')->latest('id')->paginate(40)->through(fn (AuditLog $l) => [
                'id' => $l->id, 'action' => $l->action, 'actor' => $l->actor?->only('name', 'phone'),
                'subject' => $l->subject_type ? class_basename($l->subject_type).' #'.$l->subject_id : null,
                'subject_type' => $l->subject_type ? class_basename($l->subject_type) : null, 'subject_id' => $l->subject_id,
                'data' => $l->data, 'ip' => $l->ip, 'created_at' => $l->created_at->toIso8601String(),
            ]),
        ]);
    }
}
