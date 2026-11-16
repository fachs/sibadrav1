@extends('layouts.auth', ['title' => 'Login'])

@section('content')
    <div class="col-xl-5">

        <div class="card auth-card">
            <div class="card-body px-3 py-5">
                <div class="mx-auto mb-4 text-center auth-logo">
                    <a href="{{ route('second', ['dashboards', 'analytics'])}}" class="logo-dark">
                        <img src="/images/logo-dark.png" height="80" alt="logo dark">
                    </a>

                    <a href="{{ route('second', ['dashboards', 'analytics'])}}" class="logo-light">
                        <img src="/images/logo-dark.png" height="80" alt="logo light">
                    </a>
                </div>

                <h2 class="fw-bold text-uppercase text-center fs-18">Masuk</h2>
                

                <div class="px-4">
                    <form method="POST" action="{{ route('login') }}" class="authentication-form">

                        @csrf
                        @if (sizeof($errors) > 0)
                            @foreach ($errors->all() as $error)
                                <p class="text-danger mb-3">{{ $error }}</p>
                            @endforeach
                        @endif

                        <div class="mb-3">
                            <label class="form-label" for="example-email">Email</label>
                            <input type="email" id="example-email" name="email"
                                   class="form-control bg-light bg-opacity-50 border-light py-2"
                                   placeholder="Enter your email" value="{{ old('email') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="example-password">Password</label>
                            <div class="input-group">
                                <input type="password" id="example-password"
                                       class="form-control bg-light bg-opacity-50 border-light border-end-0 py-2"
                                       placeholder="Enter your password" name="password">
                                <button type="button" class="btn border border-light border-start-0 bg-light bg-opacity-50" id="toggle-password">
                                    <i class="ri-eye-off-line" id="toggle-icon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="checkbox-signin">
                                <label class="form-check-label" for="checkbox-signin">Remember me</label>
                            </div>
                        </div>

                        <div class="mb-1 text-center d-grid">
                            <button class="btn btn-danger py-2 fw-medium" type="submit">Sign In</button>
                        </div>
                    </form>

                   


                </div> <!-- end col -->
            </div> <!-- end card-body -->
        </div> <!-- end card -->
    <script>
        document.getElementById('toggle-password').addEventListener('click', function() {
            const passwordInput = document.getElementById('example-password');
            const toggleIcon = document.getElementById('toggle-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('ri-eye-off-line');
                toggleIcon.classList.add('ri-eye-line');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('ri-eye-line');
                toggleIcon.classList.add('ri-eye-off-line');
            }
        });
    </script>
</div>
@endsection
