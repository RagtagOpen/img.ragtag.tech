<?php
  header('Content-Type: application/json');
  echo json_encode(
    array(
      'status' => 'OK',
      'php' => phpversion()
    )
  );
  die();
