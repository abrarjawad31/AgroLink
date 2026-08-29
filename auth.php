<?php

// Start session if it has not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Check if user is logged in
|--------------------------------------------------------------------------
*/

function requireLogin()
{
    if (!isset($_SESSION["user_id"])) {

        header("Location: login.php");
        exit();
    }
}


/*
|--------------------------------------------------------------------------
| Check if user is a Consumer
|--------------------------------------------------------------------------
*/

function requireConsumer()
{
    requireLogin();

    if ($_SESSION["role"] !== "consumer") {

        header("Location: index.php");
        exit();
    }
}


/*
|--------------------------------------------------------------------------
| Check if user is a Farmer
|--------------------------------------------------------------------------
*/

function requireFarmer()
{
    requireLogin();

    if ($_SESSION["role"] !== "farmer") {

        header("Location: index.php");
        exit();
    }
}


/*
|--------------------------------------------------------------------------
| Check if user is an Admin
|--------------------------------------------------------------------------
*/

function requireAdmin()
{
    requireLogin();

    if ($_SESSION["role"] !== "admin") {

        header("Location: index.php");
        exit();
    }
}

?>