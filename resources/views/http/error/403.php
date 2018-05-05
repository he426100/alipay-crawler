<html>
	<head>
		<style type="text/css">
			html, body, #wrapper {
				height:90%;
				width: 100%;
				margin: 0;
				padding: 0;
				border: 0;
			}
			#wrapper td {
				vertical-align: middle;
				text-align: center;
			}
		</style>
	</head>
	<body>
		<table id="wrapper">
			<tr>
				<td>
					<h1>Error 403!</h1>
					<?= isset($message) ? $message : "" ?>
				</td>
			</tr>
		</table>
	</body>
</html>