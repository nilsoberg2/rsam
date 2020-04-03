
var getPageClusterId = function () {
	var paramStr = window.location.search.substring(1);
	var params = paramStr.split("&");
	var reqId = "";
	for (var i = 0; i < params.length; i++) {
		var parts = params[i].split("=");
		if (parts[0] === "id")
			reqId = parts[1];
	}
	if (reqId)
		return reqId;
	else
		return "";
};

$(document).ready(function () {
    var clusterId = getPageClusterId();
    if (clusterId)
        $("#inputClusterId").val(clusterId);

    $(".needs-validation").submit(function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (this.checkValidity() === true) {
            var that = $(this);
            $.ajax({
                type: that.attr("method"),
                url: that.attr("action"),
                data: that.serialize(),
                dataType: "json",
                success: function (data) {
                    console.log(data);
                    if (data.valid) {
                        window.location.href = "submit_ok.html";
                    } else {
                        $("#errorMsg").empty().show();
                        for (var msg in data.message) {
                            $("#errorMsg").append('<div class="alert alert-warning">' + data.message[msg] + '</div>');
                        }
                    }
                },
            });
        }
        $(this).addClass("was-validated");
    });
});

