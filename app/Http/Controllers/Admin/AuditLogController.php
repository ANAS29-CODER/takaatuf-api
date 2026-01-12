<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    //
        public function index()
    {
        $auditLogs = AuditLog::all();

        return AuditLogResource::collection($auditLogs);
    }
}
