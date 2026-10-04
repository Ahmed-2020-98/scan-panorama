<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('uploads:prune')->hourly();
