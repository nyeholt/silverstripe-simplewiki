<form $FormAttributes>
	$dataFieldByName(TargetPage)
   	<% if Actions %>
    <% loop Actions %>$Field<% end_loop %>
   	<% end_if %>
</form>