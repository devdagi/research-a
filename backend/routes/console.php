<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('cdm:purge-locations')->daily();
