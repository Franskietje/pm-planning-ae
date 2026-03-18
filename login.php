<?php
require_once __DIR__ . '/session.php';
?>
<!DOCTYPE html>
<html lang="nl">
<head>

<meta charset="UTF-8">
<title>Planning Login</title>

<link rel="stylesheet" href="css/planning.css">

</head>

<body class="login-page">

<div class="login-box">

  <div class="login-title">
    <img src="img/logo.svg" alt="logo">
    Planning
  </div>

  <form id="loginForm">

    <input
      id="username"
      name="username"
      placeholder="Naam"
      required
    >

    <input
      id="password"
      name="password"
      type="password"
      placeholder="Wachtwoord"
      required
    >

    <button type="submit">Login</button>

    <div id="error" class="login-error"></div>

  </form>

</div>

<script>

const form = document.getElementById('loginForm');
const error = document.getElementById('error');

form.addEventListener('submit', async (e) =>
{
  e.preventDefault();

  error.textContent = '';

  const data = new FormData(form);

  try
  {
    const res = await fetch('api/auth-login.php',
    {
      method:'POST',
      body:data
    });

    const json = await res.json();

    if(!json.ok)
    {
      error.textContent = 'Login mislukt';
      return;
    }

    window.location.href = 'planning.php';
  }
  catch(err)
  {
    error.textContent = 'Server fout';
  }

});

</script>

</body>
</html>