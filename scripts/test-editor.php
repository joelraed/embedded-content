<?php
/** Create a non-admin fixture account with an unrecorded random password. */
$users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name' => 'inside_editor_regression']);
$user = reset($users) ?: \Drupal\user\Entity\User::create(['name' => 'inside_editor_regression', 'mail' => 'inside-editor@example.test', 'status' => 1]);
$user->setPassword(\Drupal\Component\Utility\Crypt::randomBytesBase64(32));
$user->addRole('inside_content_editor');
$user->save();
echo "Non-admin regression editor is ready.\n";
