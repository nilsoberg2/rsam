
$(document).ready(function() {
    var url = "search.php";
    var searchSeqFn = function() {
        var seq = $("#searchSeq").val();
        $.post(url, {t: "seq", query: seq}, function(dataStr) {
            var data = JSON.parse(dataStr);
            if (data.status !== true) {
                $("#searchSeqErrorMsg").text(data.message).show();
            } else {
                var processFn = function(network) {
                    var table = $('<table class="table table-sm"></table>');
    		        table.append('<thead><tr><th>Cluster</th><th>E-Value</th></thead>');
    		        var body = $('<tbody>');
            		table.append(body);
                    for (var i = 0; i < data.matches.length; i++) {
                        var netName = typeof network !== 'undefined' ? network.getNetworkMapName(data.matches[i][0]) : data.matches[i][0];
                        body.append('<tr><td><a href="explore.html?id=' + data.matches[i][0] + '">' + netName + '</a></td><td>' + data.matches[i][1] + '</td></tr>');
                    }
                    $("#searchResults").empty().append(table).show();
                    $("#searchUi").hide();
                };

                $.get("getdata.php", {a: "netinfo"}, function (netDataStr) {
                    var data = parseNetworkJson(netDataStr);
                    var network;
                    if (data !== false) {
                        if (data.valid) {
                            network = new Network("", data);
                        }
                    }
                    processFn(network);
                });
            }
        });
    };
    var searchIdFn = function() {
        var idVal = $("#searchId").val();
        $.post(url, {t: "id", query: idVal}, function(dataStr) {
            var data = JSON.parse(dataStr);
            console.log(data.status);
            if (data.status !== true) {
                $("#searchIdErrorMsg").text(data.message).show();
            } else {
                window.location.href = "explore.html?id=" + data.cluster_id;
            }
        });
    };
    var getSearchTaxType = function() {
        return $("#searchTaxTypeGenus").prop("checked") ? "genus" : ($("#searchTaxTypeFamily").prop("checked") ? "family" : "species");
    };
    var searchTaxFn = function() {
        var termVal = $("#searchTaxTerm").val();
        var termType = getSearchTaxType();
        $.post(url, {t: "tax", query: termVal, type: termType}, function(dataStr) {
            var data = JSON.parse(dataStr);
            if (data.status !== true) {
                $("#searchTaxTermErrorMsg").text(data.message).show();
            } else {
                var processFn = function(network) {
                    var table = $('<table class="table table-sm"></table>');
    		        table.append('<thead><tr><th>Cluster</th><th>Number of Hits</th></thead>');
    		        var body = $('<tbody>');
            		table.append(body);
                    for (var i = 0; i < data.matches.length; i++) {
                        var netName = typeof network !== 'undefined' ? network.getNetworkMapName(data.matches[i][0]) : data.matches[i][0];
                        if (netName)
                            body.append('<tr><td><a href="explore.html?id=' + data.matches[i][0] + '">' + netName + '</a></td><td>' + data.matches[i][1] + '</td></tr>');
                    }
                    $("#searchResults").empty().append(table).show();
                    $("#searchUi").hide();
                };

                $.get("getdata.php", {a: "netinfo"}, function (netDataStr) {
                    var data = parseNetworkJson(netDataStr);
                    var network;
                    if (data !== false) {
                        if (data.valid) {
                            network = new Network("", data);
                        }
                    }
                    processFn(network);
                });
            }
        });
    };

    var historyFn = function(type = "") {
        history.pushState({type: type}, "Search " + type);
    };
    $(window).on("popstate", function (e) {
        var state = e.originalEvent.state;
        if (state === null) {
            $("#searchResults").hide();
            $("#searchUi").show();
        } else {
            $("#searchResults").show();
            $("#searchUi").hide();
        }
    });

    $("#searchIdBtn").click(function() {
        $("#searchIdErrorMsg").hide();
        historyFn("ID");
        searchIdFn();
    });

    $("#searchSeqBtn").click(function() {
        $("#searchSeqErrorMsg").hide();
        historyFn("Sequence");
        searchSeqFn();
    });
    $("#searchTaxTermBtn").click(function() {
        $("#searchTaxTermErrorMsg").hide();
        historyFn("Taxonomy");
        searchTaxFn();
    });


    var taxSearch = new Bloodhound({
        datumTokenizer: Bloodhound.tokenizers.obj.whitespace('value'),
        queryTokenizer: Bloodhound.tokenizers.whitespace,
        prefetch: 'search.php?t=tax-prefetch',
        remote: {
            url: 'search.php?t=tax-auto&query=%QUERY',
            wildcard: '%QUERY'
            //prepare: function(query, settings) { return "search.php?t=tax-auto&query=" + query + "&type=" + getSearchTaxType(); }
        }
    });

    $("#searchTaxTerm").typeahead({
        minLength: 4,
    }, {
        source: taxSearch,
        limit: 100,
    });
});

