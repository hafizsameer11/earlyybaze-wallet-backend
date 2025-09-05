{{-- resources/views/emails/admin_login_otp.blade.php --}}
@component('mail::message')
# Hi {{ $name }},

Your one-time password (OTP) for admin login is:

# **{{ $otp }}**

This code expires in **{{ $minutes }} minutes**.  
If you didn’t request this, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
