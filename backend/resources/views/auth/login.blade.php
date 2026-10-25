@extends('layouts.app')

@section('title', 'Entrar')

@section('content')
    <div class="card" style="max-width: 400px; margin: 3rem auto;">
        <h1>Entrar</h1>
        <form method="POST" action="{{ url('login') }}">
            @csrf
            <div class="field">
                <label for="email">Correo</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="password">Contraseña</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
            </div>
            <div class="field">
                <label class="check"><input type="checkbox" name="remember" value="1"> Mantener la sesión abierta</label>
            </div>
            <button type="submit" class="btn-block">Entrar</button>
        </form>
    </div>
@endsection
