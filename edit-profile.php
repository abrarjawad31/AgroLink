```php
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "auth.php";
requireConsumer();

require_once "config.php";

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$consumerId = (int) $_SESSION['user_id'];

$errors = [];
$success = "";


/*
|--------------------------------------------------------------------------
| Fetch Current User
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT id, name, email, phone, address, status
    FROM users
    WHERE id = ? AND role = 'consumer'
    LIMIT 1
");
$stmt->bind_param("i", $consumerId);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Handle Profile Update
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name === '') {
        $errors[] = "Full name is required.";
    }

    if ($email === '') {
        $errors[] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    /*
    |--------------------------------------------------------------------------
    | Check Duplicate Email
    |--------------------------------------------------------------------------
    */
    if (empty($errors)) {

        $stmt = $conn->prepare("
            SELECT id
            FROM users
            WHERE email = ?
              AND id <> ?
            LIMIT 1
        ");

        $stmt->bind_param("si", $email, $consumerId);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $errors[] = "This email address is already being used by another account.";
        }

        $stmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | Update Database
    |--------------------------------------------------------------------------
    */
    if (empty($errors)) {

        $stmt = $conn->prepare("
            UPDATE users
            SET
                name = ?,
                email = ?,
                phone = ?,
                address = ?
            WHERE id = ?
              AND role = 'consumer'
        ");

        $stmt->bind_param(
            "ssssi",
            $name,
            $email,
            $phone,
            $address,
            $consumerId
        );

        if ($stmt->execute()) {

            $_SESSION['user_name'] = $name;

            $success = "Your profile has been updated successfully.";

            $user['name'] = $name;
            $user['email'] = $email;
            $user['phone'] = $phone;
            $user['address'] = $address;

        } else {

            $errors[] = "Unable to update your profile. Please try again.";
        }

        $stmt->close();
    }
}

$initial = strtoupper(substr(trim($user['name'] ?? 'C'), 0, 1));

if ($initial === '') {
    $initial = 'C';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Edit Profile | AgroLink</title>

    <link
        rel="stylesheet"
        href="css/edit-profile.css"
    >

</head>

<body>

<div class="edit-page">

    <div class="edit-card">

        <div class="edit-header">

            <a
                href="consumer-profile.php"
                class="back-link"
            >
                ← Back to Profile
            </a>

            <div class="edit-avatar">
                <?= e($initial) ?>
            </div>

            <h1>
                Edit Your Profile
            </h1>

            <p>
                Update your personal and delivery information.
            </p>

        </div>


        <?php if (!empty($success)): ?>

            <div class="alert success">
                <?= e($success) ?>
            </div>

        <?php endif; ?>


        <?php if (!empty($errors)): ?>

            <div class="alert error">

                <?php foreach ($errors as $error): ?>

                    <div>
                        <?= e($error) ?>
                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            action=""
            class="profile-form"
        >

            <div class="form-group">

                <label for="name">
                    Full Name
                </label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    value="<?= e($user['name']) ?>"
                    required
                    maxlength="100"
                >

            </div>


            <div class="form-group">

                <label for="email">
                    Email Address
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= e($user['email']) ?>"
                    required
                    maxlength="150"
                >

            </div>


            <div class="form-group">

                <label for="phone">
                    Phone Number
                </label>

                <input
                    type="text"
                    id="phone"
                    name="phone"
                    value="<?= e($user['phone']) ?>"
                    maxlength="30"
                    placeholder="Enter your phone number"
                >

            </div>


            <div class="form-group">

                <label for="address">
                    Delivery Address
                </label>

                <textarea
                    id="address"
                    name="address"
                    rows="4"
                    maxlength="500"
                    placeholder="Enter your delivery address"
                ><?= e($user['address']) ?></textarea>

            </div>


            <div class="account-status">

                <span>
                    Account Status
                </span>

                <strong>
                    <?= e(ucfirst($user['status'])) ?>
                </strong>

            </div>


            <div class="form-actions">

                <a
                    href="consumer-profile.php"
                    class="cancel-btn"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="save-btn"
                >
                    Save Changes
                </button>

            </div>

        </form>

    </div>

</div>

</body>

</html>
```
