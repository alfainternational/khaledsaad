<?php

namespace App\Support\Ui;

class FlashMessageCatalog
{
    public function invalidCredentials(): string
    {
        return 'تعذر تسجيل الدخول. تحقق من البريد وكلمة المرور ثم أعد المحاولة.';
    }

    public function inactiveAccount(): string
    {
        return 'حسابك غير نشط حاليًا. تواصل مع الإدارة لتفعيل الوصول.';
    }

    public function adminAccessDenied(): string
    {
        return 'هذا الحساب لا يملك صلاحية دخول لوحة الإدارة.';
    }

    public function created(string $entity): string
    {
        return 'تم إنشاء '.$entity.' بنجاح.';
    }

    public function updated(string $entity): string
    {
        return 'تم تحديث '.$entity.' بنجاح.';
    }

    public function deleted(string $entity): string
    {
        return 'تم حذف '.$entity.' بنجاح.';
    }

    public function statusUpdated(string $entity): string
    {
        return 'تم تحديث حالة '.$entity.' بنجاح.';
    }

    public function switchedWorkspace(): string
    {
        return 'تم تبديل مساحة العمل الحالية بنجاح.';
    }

    public function invitationCreated(): string
    {
        return 'تم إنشاء الدعوة وإرسالها بنجاح.';
    }

    public function invitationAccepted(): string
    {
        return 'تم قبول الدعوة وإضافتك إلى مساحة العمل.';
    }

    public function memberRemoved(): string
    {
        return 'تمت إزالة العضو من مساحة العمل.';
    }

    public function invitationDeleted(): string
    {
        return 'تم حذف الدعوة بنجاح.';
    }

    public function onboardingCompleted(): string
    {
        return 'اكتمل إعداد مساحة العمل الأولى بنجاح.';
    }

    public function approvalSubmitted(): string
    {
        return 'تم إرسال العنصر للمراجعة والاعتماد.';
    }

    public function approvalUpdated(): string
    {
        return 'تم تحديث حالة الاعتماد بنجاح.';
    }

    public function subscriptionUpdated(): string
    {
        return 'تم تحديث الاشتراك والخطة بنجاح.';
    }

    public function passwordReset(): string
    {
        return 'تمت إعادة تعيين كلمة المرور بنجاح.';
    }

    public function entitlementOverrideSaved(): string
    {
        return 'تم حفظ تعديل الصلاحية بنجاح.';
    }

    public function entitlementOverrideDeleted(): string
    {
        return 'تم حذف تعديل الصلاحية بنجاح.';
    }

    public function studioDraftGenerated(): string
    {
        return 'تم توليد مسودة جديدة وحفظها بنجاح.';
    }
}
