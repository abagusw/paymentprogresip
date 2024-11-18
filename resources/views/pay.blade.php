<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>
            Integrasi Midtrans dengan Laravel
        </title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4bw+/aepP/YC94hEpVNVgiZdgIC5+VKNBQNGCHeKRQN+PtmoHDEXuppvnDJzQIu9" crossorigin="anonymous">
    </head>
    <body>
        <main class="py-5">
            <div class="container">
                <div class="row d-flex justify-content-center">
                    <div class="col-lg-8 col-12">
                        <h2 class="fs-5 py-4 text-center">
                            Integrasi Midtrans dengan Laravel
                        </h2>
                        <div class="card border rounded shadow">
                            <div class="card-body">
                                <form id="donation-form">
                                    <div class="row mb-3">
                                        <div class="col-md-6 mb-2">
                                            <label for="client_name" class="form-label">Name</label>
                                            <input type="text" id="client_name" name="client_name" value="{{ old('client_name') }}" class="form-control @error('client_name') is-invalid @enderror" placeholder="Your Name" required>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <label for="client_email" class="form-label">Email</label>
                                            <input type="email" id="client_email" name="client_email" value="{{ old('client_email') }}" class="form-control @error('client_email') is-invalid @enderror" placeholder="Your Email">
                                        </div>
                                        <div class="col-md-12 mb-2">
                                            <label for="amount" class="form-label">Amount</label>
                                            <input type="number" id="amount" name="amount" value="{{ old('amount') }}" class="form-control @error('amount') is-invalid @enderror" required>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <button class="btn btn-primary" id="pay-button">Pay</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <script src="https://code.jquery.com/jquery-3.7.0.min.js" integrity="sha256-2Pmvv0kuTBOenSvLm6bvfBSSHrUJ+3A7x6P5Ebd07/g=" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-HwwvtgBNo3bZJJLYd8oVXjrBZt8cqVSpeBNS5n7C8IVInixGAoxmnlMuBnhbgrkm" crossorigin="anonymous"></script>    
        <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="{{ config('services.midtrans.clientKey') }}"></script>
        <script type="text/javascript">
            $('#pay-button').click(function (event) {
            event.preventDefault();
            
            $.post("{{ route('midtrans.pay') }}", {
                _method: 'POST',
                _token: '{{ csrf_token() }}',
                client_name: $('#client_name').val(),
                client_email: $('#client_email').val(),
                amount: $('#amount').val(),
            },
            function (data, status) {    
                if (data.status == 'success') {
                    if (typeof data.snap_response.error_message == "undefined") {
                        location.href = data.snap_response.redirect_url;
                    } else {
                        console.log("MIDTRANS_LOG", data.snap_response.error_message);
                        alert(data.message);
                    }
                } else {
                    console.log("ERROR_LOG", data.message);
                    alert(data.message);
                }    
                return false;
            });
            });
        </script>
    </body>
</html>