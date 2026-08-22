<?php
// -----------------------------------------------------
// Modul Logout
// -----------------------------------------------------

function module_handle(): void
{
    Auth::logout();
}

function module_render(): void
{
    // tidak pernah tampil, selalu redirect
}
