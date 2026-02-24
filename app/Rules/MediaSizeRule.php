<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MediaSizeRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        //


    }
    public function passes($attribute, $value)
    {
        $extension = strtolower($value->getClientOriginalExtension());
        $sizeKB = $value->getSize() / 1024; // الحجم بالكيلوبايت

        // الصور ≤ 10MB
        if (in_array($extension, ['jpeg','jpg','png','gif','bmp'])) {
            return $sizeKB <= 10240; // 10MB
        }

        // الفيديوهات ≤ 100MB
        if (in_array($extension, ['mp4','mov','avi','wmv','mkv'])) {
            return $sizeKB <= 102400; // 100MB
        }

        return false; // أي نوع آخر غير مسموح
    }

    public function message()
    {
        return 'Each image must be ≤ 10MB and each video ≤ 100MB.';
    }
}

