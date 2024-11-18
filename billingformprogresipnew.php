<!doctype html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css"
        integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">

    <title>Payment Sekutu Progresip</title>
</head>

<body>
<?php 
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $curl = curl_init();
    $payload = json_encode($_POST);

    $headers[] = "Content-Type: application/json";
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_URL, 'https://payment.progresip.id/api/billing');
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($curl);

    curl_close($curl);
    echo json_encode($response);
    $message = '';
    $submessage = '';
    $response = json_decode($response);
    if ($response->status == 'Success') {
        $message = 'Your payment has been created, click this link to complete your payment
        <a href="'.$response->data->payment_xendit_url.'" target="_blank">'.$response->data->payment_xendit_url.'</a>';
        $submessage = 'Please complete your transaction before '.$response->data->payment_xendit_expired;
    } else {
        $message = $response->message;
    }
}
?>
    <div class="container mt-3">
<?php 
    if ($_SERVER["REQUEST_METHOD"] == "POST") { 
        if ($response->status == 'Success') {
?>
        <div class="text-center" id="loading">
            <div class="row">
                <div class="col-sm">
                    <strong role="status">Your payment has been created, Redirect to checkout page in <span id="countdown">5</span></strong>
                </div>
            </div>
        </div>
<?php 
        } else {
?>
        <div class="text-center" id="loading">
            <div class="row">
                <div class="col-sm">
                    <strong role="status"><?php echo $message ?></strong>
                </div>
            </div>
        </div>
<?php 
        }
    } else {
?>
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">
            <div class="row">
                <div class="col-sm">
                    <div class="form-group">
                        <label for="client_email">Email Address</label>
                        <input type="email" class="form-control" id="client_email" name="client_email" placeholder="Enter email" required />
                    </div>
                    <div class="form-group">
                        <label for="client_name">Name</label>
                        <input type="text" class="form-control" id="client_name" name="client_name" placeholder="Name" required />
                    </div>
                    <div class="form-group">
                        <label for="client_phone">Phone</label>
                        <input type="text" class="form-control" id="client_phone" name="client_phone" placeholder="Phone" required />
                    </div>
                    <div class="form-group">
                        <label for="group_id">Sekutu Progresip</label>
                        <select class="form-control" id="group_id" name="group_id" required>
                            <option value="1" selected>Prekariat</option>
                            <option value="2">Proletar</option>
                            <option value="3">Kamerad</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="number_of_months">Number Of Months</label>
                        <select class="form-control" id="number_of_months" name="number_of_months" required>
                            <option value="1" selected>1 Month</option>
                            <option value="3">3 Months</option>
                            <option value="6">6 Months</option>
                            <option value="12">12 Months</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="total_payment_amount">Total Payment Amount</label>
                        <input type="text" class="form-control" id="total_payment_amount" name="total_payment_amount" disabled />
                    </div>
                    <button type="submit" class="btn btn-primary" id="submitTransaction">Submit</button>
                </div>
            </div>
        </form>
<?php 
    }
?>
    </div>
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"
        integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.14.7/dist/umd/popper.min.js"
        integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/js/bootstrap.min.js"
        integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous">
    </script>
    <script>
        function calculateTotalPaymentAmount() {
            var group_id = $("#group_id").val();
            var number_of_months = $("#number_of_months").val();
            var amount = 0;
            switch (group_id) {
                case '2':
                    amount = 100000;
                    break;
                case '3':
                    amount = 250000;
                    break;
                default:
                    amount = 30000;
                    break;
            }
            var total_payment_amount = amount * number_of_months;
            total_payment_amount_formated = total_payment_amount.toLocaleString('id-ID', {
                style: 'currency',
                currency: 'IDR'
            });
            $("#total_payment_amount").val(total_payment_amount_formated);
        }
        $(document).ready(function (e) {
            calculateTotalPaymentAmount();
<?php 
    if ($_SERVER["REQUEST_METHOD"] == "POST") { 
        if ($response->status == 'Success'){
?>
        var count = 5;
        setInterval(() => {
            count--;
            console.log(count);
            if (count == 0) {
                window.location.replace('<?php echo $response->data->payment_xendit_url ?>');
            }
            document.getElementById("countdown").innerHTML = count;
        }, 1000);
<?php 
    }}
?>
        });
        $("#group_id").change(function (e) {
            calculateTotalPaymentAmount();
        });
        $("#number_of_months").change(function (e) {
            calculateTotalPaymentAmount();
        });
    </script>
</body>

</html>
