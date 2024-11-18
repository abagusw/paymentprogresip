@extends('beautymail::templates.minty')

@section('content')

	@include('beautymail::templates.minty.contentStart')

		<tr>
			<td class="title">
				INVOICE PAID
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
				Hallo {{ $client->client_name }}, Tagihan kamu sudah terbayar(invoice number '{{ $invoice->invoice_number }}'). 
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