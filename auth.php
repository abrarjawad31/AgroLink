<?php

/*
|--------------------------------------------------------------------------
| Start Session
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Check if User is Logged In
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
| Check if User is a Consumer
|--------------------------------------------------------------------------
*/

function requireConsumer()
{
    requireLogin();

    if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "consumer") {

        if (isset($_SESSION["role"]) && $_SESSION["role"] === "farmer") {
            header("Location: farmer.php");
            exit();
        }

        if (isset($_SESSION["role"]) && $_SESSION["role"] === "admin") {
            header("Location: admin-dashboard.php");
            exit();
        }

        header("Location: index.php");
        exit();

    }
}


/*
|--------------------------------------------------------------------------
| Check if User is a Farmer
|--------------------------------------------------------------------------
*/

function requireFarmer()
{
    requireLogin();

    if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "farmer") {

        if (isset($_SESSION["role"]) && $_SESSION["role"] === "consumer") {
            header("Location: consumer-dashboard.php");
            exit();
        }

        if (isset($_SESSION["role"]) && $_SESSION["role"] === "admin") {
            header("Location: admin-dashboard.php");
            exit();
        }

        header("Location: index.php");
        exit();

    }
}


/*
|--------------------------------------------------------------------------
| Check if User is an Admin
|--------------------------------------------------------------------------
*/

function requireAdmin()
{
    requireLogin();

    if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {

        if (isset($_SESSION["role"]) && $_SESSION["role"] === "consumer") {
            header("Location: consumer-dashboard.php");
            exit();
        }

        if (isset($_SESSION["role"]) && $_SESSION["role"] === "farmer") {
            header("Location: farmer.php");
            exit();
        }

        header("Location: index.php");
        exit();

    }
}

?>