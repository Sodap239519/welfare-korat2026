<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ผู้บริหาร (executive) = ดูได้อย่างเดียว (read-only)
 * อนุญาตเฉพาะ GET/HEAD/OPTIONS · เขียน/แก้/ลบ ไม่ได้ (ยกเว้น logout)
 */
class ExecutiveReadOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            // อ่านชื่อ role จาก relation ตรง ๆ (ไม่เรียก Role::findByName ที่จะ throw
            // RoleDoesNotExist ถ้า permission cache เก่า/ยังไม่รู้จัก role — กันทุก write 500)
            $roles = $user->getRoleNames();

            $isExecutiveOnly = $roles->contains('executive')
                && $roles->intersect(['super_admin', 'admin'])->isEmpty();   // ถ้ามี role อื่นด้วย ไม่บล็อก

            if ($isExecutiveOnly
                && !in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)
                && !$request->is('api/auth/logout')           // ออกจากระบบได้
            ) {
                return response()->json([
                    'message' => 'บัญชีผู้บริหารดูข้อมูลได้อย่างเดียว — ไม่สามารถแก้ไขข้อมูลในระบบ',
                ], 403);
            }
        }

        return $next($request);
    }
}
