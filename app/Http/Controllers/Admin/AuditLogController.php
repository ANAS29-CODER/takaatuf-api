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
        // استرجاع السجلات مع تحميل البيانات المتعلقة بها
        $auditLogs = AuditLog::all();

        // استخدام AuditLogResource لعرض السجلات بتنسيق منظم
        return AuditLogResource::collection($auditLogs);
    }
}
