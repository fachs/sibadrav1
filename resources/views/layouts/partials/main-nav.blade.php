<div class="main-nav">
     <!-- Sidebar Logo -->
     <div class="logo-box">
          <a href="/" class="logo-dark">
               <img src="/images/logo-sm.png" class="logo-sm" alt="logo sm">
               <img src="/images/logo-dark.png" class="logo-lg" alt="logo dark">
          </a>

          <a href="/" class="logo-light">
               <img src="/images/logo-sm.png" class="logo-sm" alt="logo sm">
               <img src="/images/logo-light.png" class="logo-lg" alt="logo light">
          </a>
     </div>

     <!-- Menu Toggle Button (sm-hover) -->
     <button type="button" class="button-sm-hover" aria-label="Show Full Sidebar">
          <i class="ri-menu-2-line fs-24 button-sm-hover-icon"></i>
     </button>

     <div class="scrollbar" data-simplebar>

          <ul class="navbar-nav" id="navbar-nav">

               <li class="nav-item">
                    <a class="nav-link" href="{{ route('index') }}">
                         <span class="nav-icon"><i class="ri-dashboard-line"></i></span>
                         <span class="nav-text">Home</span>
                    </a>
               </li>
               <li class="menu-title">Perencanaan</li>
               <li class="nav-item">
                    <a class="nav-link menu-arrow" href="#sidebarProperty" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarProperty">
                         <span class="nav-icon">
                              <i class="ri-building-3-line"></i>
                         </span>
                         <span class="nav-text"> Rencana Kebutuhan </span>
                    </a>
                    <div class="collapse" id="sidebarProperty">
                         <ul class="nav sub-navbar-nav">
                              <li class="sub-nav-item">
                                   <a class="sub-nav-link" href="{{ route('rencana-kebutuhan.index')}}">Rincian Rencana Kebutuhan</a>
                              </li>
                              <li class="sub-nav-item">
                                   <a class="sub-nav-link" href="{{ route('renbut-per-unit')}}">Rekap per Unit</a>
                              </li>
                         </ul>
                    </div>
               </li> <!-- end Pages Menu -->

               <li class="nav-item">
                    <a class="nav-link menu-arrow" href="#sidebarDashboards" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarDashboards">
                         <span class="nav-icon">
                              <i class="ri-file-list-3-line"></i>
                         </span>
                         <span class="nav-text"> RBA </span>
                    </a>
                    <div class="collapse" id="sidebarDashboards">
                         <ul class="nav sub-navbar-nav">
                              <li class="sub-nav-item">
                                   <a class="sub-nav-link" href="{{ route('rba.pendapatan')}}">RBA Pendapatan</a>
                              </li>
                              <li class="sub-nav-item">
                                   <a class="sub-nav-link" href="{{ route('rba.belanja')}}">RBA Belanja</a>
                              </li>
                              <li class="sub-nav-item">
                                   <a class="sub-nav-link" href="{{ route('rba.pembiayaan')}}">RBA Pembiayaan</a>
                              </li>
                              <li class="sub-nav-item">
                                   <a class="sub-nav-link" href="{{ route('rba.ringkasan')}}">Ringkasan RBA</a>
                              </li>
                              <li class="sub-nav-item">
                                   <a class="sub-nav-link" href="{{ route('rba.perkiraan-harga') }}">Perkiraan Harga</a>
                              </li>
                         </ul>
                    </div>
               </li>

               <li class="nav-item">
                    <a class="nav-link menu-arrow" href="#sidebarRbaPerubahan" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarRbaPerubahan">
                         <span class="nav-icon">
                              <i class="ri-file-edit-line"></i>
                         </span>
                         <span class="nav-text"> RBA Perubahan </span>
                    </a>
                    <div class="collapse" id="sidebarRbaPerubahan">
                         <ul class="nav sub-navbar-nav">
                              <li class="sub-nav-item">
                                   <a class="sub-nav-link" href="{{ route('rba-perubahan.pendapatan') }}">RBA Pendapatan</a>
                              </li>
                              <li class="sub-nav-item">
                                   <a class="sub-nav-link" href="{{ route('rba-perubahan.belanja') }}">RBA Belanja</a>
                              </li>
                              <li class="sub-nav-item">
                                   <a class="sub-nav-link" href="{{ route('rba-perubahan.pembiayaan') }}">RBA Pembiayaan</a>
                              </li>
                              <li class="sub-nav-item">
                                   <a class="sub-nav-link" href="{{ route('rba-perubahan.ringkasan') }}">Ringkasan Perubahan RBA</a>
                              </li>
                         </ul>
                    </div>
               </li>

               <li class="nav-item">
                    <a class="nav-link" href="{{ route('rba.ambang-batas') }}">
                         <span class="nav-icon">
                              <i class="ri-percent-line"></i>
                         </span>
                         <span class="nav-text"> Ambang Batas </span>
                    </a>
               </li>

               <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('prakiraan-maju.*') ? 'active' : '' }}" href="{{ route('prakiraan-maju.index') }}">
                         <span class="nav-icon"><i class="ri-line-chart-line"></i></span>
                         <span class="nav-text">Prakiraan Maju</span>
                    </a>
               </li>

               <li class="nav-item">
                    <a class="nav-link menu-arrow {{ request()->routeIs('dokumen-rba.*') ? 'active' : '' }}" href="#sidebarDokumenRba" data-bs-toggle="collapse"
                         role="button" aria-expanded="{{ request()->routeIs('dokumen-rba.*') ? 'true' : 'false' }}" aria-controls="sidebarDokumenRba">
                         <span class="nav-icon"><i class="ri-file-text-line"></i></span>
                         <span class="nav-text">Dokumen RBA</span>
                    </a>
                    <div class="collapse {{ request()->routeIs('dokumen-rba.*') ? 'show' : '' }}" id="sidebarDokumenRba">
                         <ul class="nav sub-navbar-nav">
                              @foreach (['murni' => 'RBA Murni', 'perubahan' => 'RBA Perubahan'] as $dokumenJenis => $dokumenLabel)
                                   <li class="sub-nav-item">
                                        <a class="sub-nav-link {{ request()->routeIs('dokumen-rba.*') && request()->route('jenis') === $dokumenJenis ? 'active' : '' }}"
                                             href="{{ route('dokumen-rba.show', ['jenis' => $dokumenJenis]) }}">{{ $dokumenLabel }}</a>
                                   </li>
                              @endforeach
                         </ul>
                    </div>
               </li>

               <li class="menu-title">Penatausahaan</li>

               <li class="nav-item">
                    <a class="nav-link" href="{{ route('angkas.index')}}">
                         <span class="nav-icon">
                              <i class="ri-wallet-3-line"></i>
                         </span>
                         <span class="nav-text">Anggaran Kas</span>
                    </a>
               </li>

               <li class="nav-item">
                    <a class="nav-link menu-arrow" href="#sidebarPages" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarPages">
                         <span class="nav-icon">
                              <i class="ri-hand-coin-line"></i>
                         </span>
                         <span class="nav-text"> Pendapatan </span>
                    </a>
                    <div class="collapse" id="sidebarPages">
                         <ul class="nav sub-navbar-nav">
                              <li class="sub-nav-item">
                                   <a class="sub-nav-link" href="{{ route('penerimaan.index')}}">Penerimaan</a>
                              </li>
                              <li class="sub-nav-item">
                                   <a class="sub-nav-link" href="{{ route('pengembalian.index')}}">Pengembalian</a>
                              </li>

                         
                         </ul>
                    </div>
               </li> <!-- end Pages Menu -->

               <li class="nav-item">
                    <a class="nav-link" href="{{ route('pencairan-up.index') }}">
                         <span class="nav-icon">
                              <i class="ri-money-dollar-circle-line"></i>
                         </span>
                         <span class="nav-text"> UP </span>
                    </a>
               </li>

               

               <li class="nav-item">
                    <a class="nav-link" href="{{ route('pencairan-gu.index') }}">
                         <span class="nav-icon">
                              <i class="ri-money-dollar-circle-line"></i>
                         </span>
                         <span class="nav-text"> GU </span>
                    </a>
               </li>

               <li class="nav-item">
                    <a class="nav-link" href="{{ route('pencairan-ls.index') }}">
                         <span class="nav-icon">
                              <i class="ri-money-dollar-circle-line"></i>
                         </span>
                         <span class="nav-text"> LS </span>
                    </a>
               </li>

               @if(Auth::user()->hasAnyRole(['Pejabat Keuangan', 'Bendahara Pengeluaran', 'Kepala Puskesmas']) || Auth::user()->isDinasKesehatan())
               <li class="nav-item">
                    <a class="nav-link menu-arrow" href="#sidebarBku" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarBku">
                         <span class="nav-icon">
                              <i class="ri-book-3-line"></i>
                         </span>
                         <span class="nav-text"> Buku Kas Umum </span>
                    </a>
                    <div class="collapse" id="sidebarBku">
                         <ul class="nav sub-navbar-nav">
                              @if(Auth::user()->hasAnyRole(['Pejabat Keuangan', 'Kepala Puskesmas']) || Auth::user()->isDinasKesehatan())
                              <li class="sub-nav-item">
                                   <a class="sub-nav-link" href="{{ route('bku-pejabat-keuangan.index')}}">BKU Pejabat Keuangan</a>
                              </li>
                              @endif
                              @if(Auth::user()->hasAnyRole(['Bendahara Pengeluaran', 'Kepala Puskesmas']) || Auth::user()->isDinasKesehatan())
                              <li class="sub-nav-item">
                                   <a class="sub-nav-link" href="{{ route('bku-bendahara-pengeluaran.index')}}">BKU Bendahara Pengeluaran</a>
                              </li>
                              @endif
                              @if(Auth::user()->hasRole('Kepala Puskesmas') || Auth::user()->isDinasKesehatan())
                              <li class="sub-nav-item">
                                   <a class="sub-nav-link" href="{{ route('bku-bendahara-pengajuan.index') }}">Pengajuan Perubahan BKU</a>
                              </li>
                              @endif
                         </ul>
                    </div>
               </li>
               @endif

               <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('lra.*') ? 'active' : '' }}" href="{{ route('lra.index') }}">
                         <span class="nav-icon"><i class="ri-bar-chart-box-line"></i></span>
                         <span class="nav-text">LRA</span>
                    </a>
               </li>
               <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('lpj.*') ? 'active' : '' }}" href="{{ route('lpj.index') }}">
                         <span class="nav-icon"><i class="ri-file-list-3-line"></i></span>
                         <span class="nav-text">LPJ</span>
                    </a>
               </li>

               <li class="menu-title">Pengaturan</li>

               <li class="nav-item">
                    <a class="nav-link" href="{{ route('data-user.index')}}">
                         <span class="nav-icon">
                              <i class="ri-file-user-line"></i>
                         </span>
                         <span class="nav-text">Data User</span>
                    </a>
               </li>
               <li class="nav-item">
                    <a class="nav-link" href="{{ route('data-uptd.index')}}">
                         <span class="nav-icon">
                              <i class="ri-hospital-line"></i>
                         </span>
                         <span class="nav-text">Data UPTD</span>
                    </a>
               </li>
               <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('rekening-blud.*') ? 'active' : '' }}" href="{{ route('rekening-blud.index') }}">
                         <span class="nav-icon">
                              <i class="ri-bank-card-line"></i>
                         </span>
                         <span class="nav-text">Rekening BLUD</span>
                    </a>
               </li>
          </ul>
     </div>
</div>
