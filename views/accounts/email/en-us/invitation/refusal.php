<?php defined('SYSPATH') or die('No direct script access.'); ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?= $invitee_email; ?> declined your invitation to <?= APPNAME; ?></title>
<body>
<p>
    <strong>Hi <?= $inviter_data['first_name']; ?></strong>
</p>
<p>
    We wanted to let you know that <?= $invitee_email; ?> declined your invitation to join <?= APPNAME; ?>.<br />

    <?php if (isset($message)): ?>
        <p>
            <hr/>
                <?= $message; ?>
            <hr/>
        </p>
    <?php endif; ?>

    Feel free to send an email to <a href="mailto:<?= $invitee_email; ?>"><?= $invitee_email; ?></a> if you still want them to participate.
</p>
</body>
</html>