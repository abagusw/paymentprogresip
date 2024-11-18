@extends('beautymail::templates.minty')

@section('content')

	@include('beautymail::templates.minty.contentStart')

		<tr>
			<td class="title">
				INVOICE UNPAID
			</td>
		</tr>
		<tr>
			<td width="100%" height="10"></td>
		</tr>
		<tr>
			<td class="paragraph">
				Kepada <br>
				{{ $client->client_name }}
			</td>
		</tr>
		<tr>
			<td width="100%" height="10"></td>
		</tr>
		<tr>
			<td class="paragraph">
				<?php $date_timestamp = strtotime($subscription->subscribed_end); ?>
				Hallo {{ $client->client_name }}, Kamu punya tagihan yang belum terbayar(invoice number '{{ $invoice->invoice_number }}'). Langganan anda akan nonaktif pada tanggal {{ date( 'd-m-Y',$date_timestamp) }} jika invoice ini belum dibayar sampai tanggal tersebut. 
			</td>
		</tr>
		<tr>
			<td width="100%" height="10"></td>
		</tr>
		<tr>
			<td class="paragraph">
				Terima Kasih <br>
				Billing Admin - Project Multatuli
			</td>
		</tr>
		<tr>
			<td width="100%" height="25"></td>
		</tr>

	@include('beautymail::templates.minty.contentEnd')

@stop