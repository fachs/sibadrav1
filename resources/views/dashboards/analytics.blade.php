@extends('layouts.vertical', ['title' => 'Analytics','subTitle' => 'Dashboard'])

@section('content')
    <div class="row">
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center justify-content-between">
                        <div class="col-6">
                            <div class="avatar-md bg-light bg-opacity-50 rounded">
                                <iconify-icon icon="solar:buildings-2-broken"
                                              class="fs-32 text-primary avatar-title"></iconify-icon>
                            </div>
                            <p class="text-muted mb-2 mt-3">No. of Properties</p>
                            <h3 class="text-dark fw-bold d-flex align-items-center gap-2 mb-0">2,854 <span
                                    class="badge text-success bg-success-subtle fs-12"><i class="ri-arrow-up-line"></i>7.34%</span>
                            </h3>
                        </div> <!-- end col -->
                        <div class="col-6">
                            <div id="total_customers" class="apex-charts"></div>
                        </div> <!-- end col -->
                    </div> <!-- end row-->
                </div> <!-- end card body -->
            </div> <!-- end card -->
        </div> <!-- end col -->
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center justify-content-between">
                        <div class="col-6">
                            <div class="avatar-md bg-light bg-opacity-50 rounded">
                                <iconify-icon icon="solar:users-group-two-rounded-broken"
                                              class="fs-32 text-primary avatar-title"></iconify-icon>
                            </div>
                            <p class="text-muted mb-2 mt-3">Regi. Agents</p>
                            <h3 class="text-dark fw-bold d-flex align-items-center gap-2 mb-0">705 <span
                                    class="badge text-success bg-success-subtle fs-12"><i class="ri-arrow-up-line"></i>76.89%</span>
                            </h3>
                        </div> <!-- end col -->
                        <div class="col-6 text-end">
                            <div id="invoiced_customers" class="apex-charts"></div>
                        </div> <!-- end col -->
                    </div> <!-- end row-->
                </div> <!-- end card body -->
            </div> <!-- end card -->
        </div> <!-- end col -->
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center justify-content-between">
                        <div class="col-5">
                            <div class="avatar-md bg-light bg-opacity-50 rounded">
                                <iconify-icon icon="solar:shield-user-broken"
                                              class="fs-32 text-primary avatar-title"></iconify-icon>
                            </div>
                            <p class="text-muted mb-2 mt-3">Customers</p>
                            <h3 class="text-dark fw-bold d-flex align-items-center gap-2 mb-0">9,431 <span
                                    class="badge text-danger bg-danger-subtle fs-12"><i class="ri-arrow-down-line"></i>45.00%</span>
                            </h3>
                        </div> <!-- end col -->
                        <div class="col-6 text-end">
                            <div id="new_sale" class="apex-charts"></div>
                        </div> <!-- end col -->
                    </div> <!-- end row-->
                </div> <!-- end card body -->
            </div> <!-- end card -->
        </div> <!-- end col -->
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center justify-content-between">
                        <div class="col-5">
                            <div class="avatar-md bg-light bg-opacity-50 rounded">
                                <iconify-icon icon="solar:money-bag-broken"
                                              class="fs-32 text-primary avatar-title"></iconify-icon>
                            </div>
                            <p class="text-muted mb-2 mt-3">Revenue</p>
                            <h3 class="text-dark fw-bold d-flex align-items-center gap-2 mb-0">$78.3M <span
                                    class="badge text-success bg-success-subtle fs-12"><i class="ri-arrow-up-line"></i>8.76%</span>
                            </h3>
                        </div> <!-- end col -->
                        <div class="col-6 text-end">
                            <div id="invoiced_sales" class="apex-charts"></div>
                        </div> <!-- end col -->
                    </div> <!-- end row-->
                </div> <!-- end card body -->
            </div> <!-- end card -->
        </div> <!-- end col -->
    </div> <!-- end row -->
    <div class="row">
        <div class="col-xl-8">
            <div class="card overflow-hidden">
                <div class="card-header d-flex justify-content-between align-items-center pb-1">
                    <div>
                        <h4 class="card-title">Sales Analytic</h4>
                    </div>
                    <div class="dropdown">
                        <a href="#" class="dropdown-toggle btn btn-sm btn-outline-light rounded"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            This Month
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <!-- item-->
                            <a href="#!" class="dropdown-item">Week</a>
                            <!-- item-->
                            <a href="#!" class="dropdown-item">Months</a>
                            <!-- item-->
                            <a href="#!" class="dropdown-item">Years</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="text-end">
                        <p class="mb-0 fs-18 fw-medium text-dark"><i class="ri-wallet-3-fill"></i> Earnings : <span
                                class="text-primary fw-bold">$85,934</span></p>
                    </div>
                    <div class="row align-items-top text-center">
                        <div class="col-lg-12">
                            <div class="apex-charts mt-2" id="sales_analytic"></div>
                        </div>

                    </div>
                </div>
                <div class="card-footer p-2 bg-light-subtle text-center">
                    <div class="row g-3">
                        <div class="col-md-4 border-end">
                            <p class="text-muted mb-1">Income</p>
                            <p class="text-dark fs-18 fw-medium d-flex align-items-center justify-content-center gap-2 mb-0">
                                23,675.00 <span class="badge text-success bg-success-subtle fs-12"><i
                                        class="ri-arrow-up-line"></i>0.08%</span></p>
                        </div>
                        <div class="col-md-4 border-end">
                            <p class="text-muted mb-1">Expenses</p>
                            <p class="text-dark fs-18 fw-medium d-flex align-items-center justify-content-center gap-2 mb-0">
                                11,562.00 <span class="badge text-danger bg-danger-subtle fs-12"><i
                                        class="ri-arrow-down-line"></i>5.38%</span></p>
                        </div>
                        <div class="col-md-4">
                            <p class="text-muted mb-1">Balance</p>
                            <p class="text-dark fs-18 fw-medium d-flex align-items-center justify-content-center gap-2 mb-0">
                                67,365.00 <span class="badge text-success bg-success-subtle fs-12"><i
                                        class="ri-arrow-up-line"></i>2.89%</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card bg-primary bg-gradient">
                <div class="card-body">
                    <div class="row align-items-center justify-content-between">
                        <div class="col-xl-7 col-lg-6 col-md-6">
                            <h3 class="text-white fw-bold">$117,000.43</h3>
                            <p class="text-white-50">My Balance</p>
                            <div class="row mt-4">
                                <div class="col-lg-6 col-lg-6 col-md-6 col-6">
                                    <div class="d-flex gap-2">
                                        <div class="avatar-sm flex-shrink-0">
                                                                      <span
                                                                          class="avatar-title bg-success bg-opacity-50 text-white rounded">
                                                                           <i class="ri-arrow-down-line fs-4"></i>
                                                                      </span>
                                        </div>
                                        <div class="d-block">
                                            <h5 class="text-white fw-medium mb-0">$13,321.12</h5>
                                            <p class="mb-0 text-white-50">Income</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6 col-lg-6 col-md-6 col-6">
                                    <div class="d-flex gap-2">
                                        <div class="avatar-sm flex-shrink-0">
                                                                      <span
                                                                          class="avatar-title bg-danger bg-opacity-50 text-white rounded">
                                                                           <i class="ri-arrow-up-line fs-4"></i>
                                                                      </span>
                                        </div>
                                        <div class="d-block">
                                            <h5 class="text-white fw-medium mb-0">$7,566.11</h5>
                                            <p class="mb-0 text-white-50">Expanse</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3 g-2">
                                <div class="col-xl-6 col-lg-6 col-md-6">
                                    <a href="#!" class="btn btn-warning w-100 btn-sm">Send</a>
                                </div>
                                <div class="col-xl-6 col-lg-6 col-md-6">
                                    <a href="#!" class="btn bg-light bg-opacity-25 text-white w-100 btn-sm">Receive</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-5 col-lg-4 col-md-4">
                            <img src="/images/money.png" alt="" class="img-fluid">
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body p-0">
                    <div class="row g-3">
                        <div class="col-xl-6 col-md-6">
                            <div class="text-center p-3 border-end">
                                <h5 class="card-title mb-0 text-dark fw-medium">Property</h5>
                                <div class="avatar-md bg-light bg-opacity-50 rounded mx-auto my-3">
                                    <iconify-icon icon="solar:home-bold-duotone"
                                                  class="fs-32 text-primary avatar-title"></iconify-icon>
                                </div>
                                <h4 class="text-dark fw-medium">15,780</h4>

                                <p class="text-muted">60% Target</p>

                                <div class="progress mt-3" style="height: 10px;">
                                    <div
                                        class="progress-bar progress-bar  progress-bar-striped progress-bar-animated bg-primary"
                                        role="progressbar" style="width: 60%" aria-valuenow="70" aria-valuemin="0"
                                        aria-valuemax="70"></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-6 col-md-6">
                            <div class="text-center p-3">
                                <h5 class="card-title mb-0 text-dark fw-medium">Revenue</h5>
                                <div class="avatar-md bg-light bg-opacity-50 rounded mx-auto my-3">
                                    <iconify-icon icon="solar:money-bag-bold-duotone"
                                                  class="fs-32 text-success avatar-title"></iconify-icon>
                                </div>
                                <h4 class="text-dark fw-medium">$78.3M</h4>

                                <p class="text-muted">80% Target</p>

                                <div class="progress mt-3" style="height: 10px;">
                                    <div
                                        class="progress-bar progress-bar  progress-bar-striped progress-bar-animated bg-success"
                                        role="progressbar" style="width: 80%" aria-valuenow="80" aria-valuemin="0"
                                        aria-valuemax="80">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer border-top mt-1">
                    <a href="#!" class="link-dark fw-medium">View More <i class="ri-arrow-right-line"></i></a>
                </div>
            </div>

        </div>
    </div>

@endsection

@section('script')
    @vite(['resources/js/pages/dashboard-analytics.js'])
@endsection
