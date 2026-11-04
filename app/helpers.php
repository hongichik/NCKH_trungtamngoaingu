<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    /**
     * Site setting edited in Admin > Cài đặt website, e.g. setting('contact_address').
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}
