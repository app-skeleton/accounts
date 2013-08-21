<?php defined('SYSPATH') or die('No direct script access.'); ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title> You’ve been added to the <?= $projects[0]['name']; ?> project on App name</title>
<body>
<p>
    <?= $inviter_data['first_name'].' '.$inviter_data['last_name']; ?> added you to this project on App name:

    <p><strong><?= $projects[0]['name']; ?></strong></p>
    <p><?= $projects[0]['description']; ?></p>
</p>

<?php if ( ! $is_linked): ?>
    <p><a href="<?= $accept_url; ?>">Accept this invitation</a></p>
<?php endif; ?>

<?php if ( ! $is_linked): ?>
    <p>You can also <a href="<?= $decline_url; ?>">decline this invitation</a>.</p>
<?php endif; ?>
</body>
</html>