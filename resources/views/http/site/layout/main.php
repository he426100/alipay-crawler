<!DOCTYPE html>
<html lang="en">
	<head>

		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">

		<title></title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta name="description" content="">
		<meta name="keywords" content="">

		<!-- JQUERY  -->
		<script src="https://code.jquery.com/jquery-3.1.1.min.js"
			integrity="sha256-hVVnYaiADRTO2PzUGmuLJr8BLUSjGIZsDYGmIJLv2b8="
			crossorigin="anonymous"></script>

		<!-- BOOTSTRAP -->
		<link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>

		<!-- FONT AWESOME -->
		<link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">

		<link href="https://fonts.googleapis.com/css?family=Oswald" rel="stylesheet">

	</head>
<body>
<table id="wrapper" width="100%">
	<tr>
		<td> <!-- Header --> </td>
	</tr>
	<tr>
		<td align="center" style="font-family: 'Oswald', sans-serif; font-size: 55px">
			<?= $this->section('content') ?> <br/>
		</td>
	</tr>
	<tr>
		<td> <!-- Footer --> </td>
	</tr>
</table>
</body>
</html>