<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login — Company Device Manager</title>
    <style>
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center;
            font-family: "Segoe UI", system-ui, sans-serif;
            background: radial-gradient(circle at top, #1e3a5f, #0f172a 55%);
            color: #e2e8f0;
        }
        .card {
            width: min(420px, 92vw);
            background: rgba(30, 41, 59, 0.95);
            border: 1px solid #475569;
            border-radius: 1rem;
            padding: 1.75rem;
        }
        h1 { margin: 0 0 0.35rem; font-size: 1.4rem; }
        p { color: #94a3b8; margin-top: 0; }
        label { display:block; margin: 0.85rem 0 0.35rem; color:#94a3b8; font-size:0.85rem; }
        input {
            width: 100%; box-sizing: border-box; padding: 0.65rem 0.75rem;
            border-radius: 0.5rem; border: 1px solid #475569; background: #0f172a; color: #e2e8f0;
        }
        button {
            margin-top: 1.1rem; width: 100%; padding: 0.7rem;
            border: 0; border-radius: 0.55rem; background: #0ea5e9; color: #042f4a;
            font-weight: 700; cursor: pointer;
        }
        .error { color: #f87171; font-size: 0.9rem; margin-top: 0.75rem; }
    </style>
</head>
<body>
<div class="card">
    <h1>Company Device Manager</h1>
    <p>Administrator sign-in</p>
    <form method="POST" action="{{ url('/admin/login') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required>
        <label style="display:flex;align-items:center;gap:0.4rem;">
            <input type="checkbox" name="remember" value="1" style="width:auto;"> Remember me
        </label>
        <button type="submit">Sign in</button>
        @error('email')
            <div class="error">{{ $message }}</div>
        @enderror
    </form>
</div>
</body>
</html>
