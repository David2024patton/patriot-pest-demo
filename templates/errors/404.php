<?php /** errors/404.php — standalone (no layout) to avoid render recursion. */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>404 Target Not Found | Patriot Pest Control</title>
  <meta name="robots" content="noindex">
  <style>
    :root{--olive-950:#0d0f08;--olive-800:#1b1e14;--khaki:#c9c2a6;--orange:#e8762d;--cream:#f2efe2}
    *{margin:0;box-sizing:border-box}
    body{background:var(--olive-950);color:var(--khaki);font-family:'Barlow',Arial,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:2rem}
    .box{border:1px solid #3a3f2c;background:var(--olive-800);padding:3rem;text-align:center;max-width:480px;clip-path:polygon(0 0,calc(100% - 18px) 0,100% 18px,100% 100%,18px 100%,0 calc(100% - 18px))}
    .code{font-family:'Black Ops One',Impact,sans-serif;font-size:4.5rem;color:var(--orange);line-height:1}
    h1{font-family:'Black Ops One',Impact,sans-serif;color:var(--cream);font-size:1.3rem;margin:.8rem 0 .5rem;letter-spacing:.04em}
    p{font-size:.95rem;line-height:1.6;margin-bottom:1.5rem}
    a.btn{display:inline-block;background:var(--orange);color:#12140d;font-weight:700;text-decoration:none;padding:.8rem 1.6rem;border-radius:3px}
    a.btn:hover{filter:brightness(1.1)}
  </style>
</head>
<body>
  <div class="box">
    <div class="code">404</div>
    <h1>TARGET NOT FOUND</h1>
    <p>The page you're looking for doesn't exist or has been moved. Regroup and head back to base.</p>
    <a class="btn" href="/">Return Home</a>
  </div>
</body>
</html>
