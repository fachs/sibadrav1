<header class="">
     <div class="topbar">
     <div class="container-fluid">
          <div class="navbar-header">
               <div class="d-flex align-items-center gap-2">
                    <!-- Menu Toggle Button -->
                    <div class="topbar-item">
                         <button type="button" class="button-toggle-menu topbar-button">
                              <i class="ri-menu-2-line fs-24"></i>
                         </button>
                    </div>

                    @include('partials.tahun-anggaran-global')
               </div>

               <div class="d-flex align-items-center gap-1">
                    <!-- Theme Color (Light/Dark) -->
                    <div class="topbar-item">
                         <button type="button" class="topbar-button" id="light-dark-mode">
                              <i class="ri-moon-line fs-24 light-mode"></i>
                              <i class="ri-sun-line fs-24 dark-mode"></i>
                         </button>
                    </div>

                    <!-- Category -->
                    <div class="dropdown topbar-item d-none d-lg-flex">
                         <button type="button" class="topbar-button" data-toggle="fullscreen">
                              <i class="ri-fullscreen-line fs-24 fullscreen"></i>
                              <i class="ri-fullscreen-exit-line fs-24 quit-fullscreen"></i>
                         </button>
                    </div>

                    <!-- User -->
                    <div class="dropdown topbar-item">
                         <a type="button" class="topbar-button" id="page-header-user-dropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                              <span class="d-flex align-items-center">
                                   <img class="rounded-circle" width="32" src="/images/users/avatar-1.jpg" alt="avatar-3">
                              </span>
                         </a>
                         <div class="dropdown-menu dropdown-menu-end">
                              <!-- item-->
                              <h6 class="dropdown-header">{{ auth()->user()->name ?? 'User' }}</h6>
                              <div class="px-3 pb-2 small text-muted">{{ implode(', ', auth()->user()->roleNames()) }}</div>

                              {{-- <a class="dropdown-item" href="{{ route('second', ['pages', 'calendar'])}}">
                                   <iconify-icon icon="solar:calendar-broken" class="align-middle me-2 fs-18"></iconify-icon><span class="align-middle">My Schedules</span>
                              </a>

                              <a class="dropdown-item" href="{{ route('second', ['pages', 'pricing'])}}">
                                   <iconify-icon icon="solar:wallet-broken" class="align-middle me-2 fs-18"></iconify-icon><span class="align-middle">Pricing</span>
                              </a>
                              <a class="dropdown-item" href="{{ route('second', ['pages', 'faqs'])}}">
                                   <iconify-icon icon="solar:help-broken" class="align-middle me-2 fs-18"></iconify-icon><span class="align-middle">Help</span>
                              </a>
                              <a class="dropdown-item" href="{{ route('second', ['pages', 'lock-screen'])}}">
                                   <iconify-icon icon="solar:lock-keyhole-broken" class="align-middle me-2 fs-18"></iconify-icon><span class="align-middle">Lock screen</span>
                              </a> --}}

                              <div class="dropdown-divider my-1"></div>

                              <a class="dropdown-item text-danger" href="{{ route('second', ['auth', 'login'])}}">
                                   <iconify-icon icon="solar:logout-3-broken" class="align-middle me-2 fs-18"></iconify-icon><span class="align-middle">Logout</span>
                              </a>
                         </div>
                    </div>
               </div>
          </div>
     </div></div>
</header>
