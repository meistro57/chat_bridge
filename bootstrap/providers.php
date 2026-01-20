<?php

return [
    App\Providers\AppServiceProvider::class,
    ...app()->environment('local') ? [App\Providers\TelescopeServiceProvider::class] : [],
];
