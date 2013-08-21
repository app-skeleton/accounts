<?php defined('SYSPATH') or die('No direct script access.'); ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title> You’ve been added to <?= count($projects); ?> projects on App name</title>
<body>
<p>
    <?= $inviter_data['first_name'].' '.$inviter_data['last_name']; ?> added you to these projects on App name:

    <?php foreach ($projects as $project): ?>
    <p>
        <strong><?= $project['name']; ?></strong>
    </p>
    <p>
        <?= $project['description']; ?>
    </p>
    <?php endforeach; ?>
</p>

<?php if ( ! $is_linked): ?>
    <p><a href="<?= $accept_url; ?>">Accept this invitation</a></p>
<?php endif; ?>

<?php if ( ! $is_linked): ?>
    <p>You can also <a href="<?= $decline_url; ?>">decline this invitation</a>.</p>
<?php endif; ?>
</body>
</html>