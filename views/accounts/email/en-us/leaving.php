<?php defined('SYSPATH') or die('No direct script access.'); ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?= $invitee_data['first_name'].' '.$invitee_data['last_name']; ?> left your account.</title>
<body>
<p>
    Hi <?= $account_owner_data['first_name']; ?>,
</p>
<p>
    We wanted to let you know that <?= $invitee_data['first_name'].' '.$invitee_data['last_name']; ?> left your <?= APPNAME; ?> account.<br />

    <?php if (isset($message)): ?>
        <p>
            <hr/>
                <?= $message; ?>
            <hr/>
        </p>
    <?php endif; ?>

    Feel free to send an email to <a href="mailto:<?= $invitee_data['email']; ?>"><?= $invitee_data['email']; ?></a> if you still want them to participate.
</p>
</body>
</html>