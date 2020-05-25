
$(document).ready(function() {
    var searchApp = "dosearch.php";

    var getVersion = function() {
        var v = $("#version").val();
        return v;
    };

    var getNetInfo = function(version, onFinish) {
        $.get("getdata.php", {a: "netinfo", v: version}, function (netDataStr) {
            var netData = parseNetworkJson(netDataStr);
            var network;
            if (netData !== false) {
                if (netData.valid) {
                    network = new Network("", netData);
                }
            }
            if (network) {
                onFinish(network);
            }
        });
    }

    var searchSeqFn = function() {
        var seq = $("#searchSeq").val();
        var version = getVersion();
        var progress = new Progress($("#progressLoader"));
        progress.start();
        $.post(searchApp, {t: "seq", query: seq, v: version}, function(dataStr) {
            var data = JSON.parse(dataStr);
            if (data.status !== true) {
                $("#searchSeqErrorMsg").text(data.message).show();
            } else {
                var processFn = function(network, matches, parentCluster = "", ascore = "") {
                    var ascoreUrl = (parentCluster && ascore) ? "&as=" + ascore : "";
                    var table = $('<table class="table table-sm"></table>');
    		        table.append('<thead><tr><th>Cluster</th><th>E-Value</th></thead>');
    		        var body = $('<tbody>');
            		table.append(body);
                    console.log(matches);
                    for (var i = 0; i < matches.length; i++) {
                        var netName = typeof network !== 'undefined' ? network.getNetworkMapName(matches[i][0]) : matches[i][0];
                        console.log(netName);
                        body.append('<tr><td><a href="explore.html?v=' + version + '&id=' + matches[i][0] + ascoreUrl + '">' + netName + '</a></td><td>' + matches[i][1] + '</td></tr>');
                    }
                    if (parentCluster && ascore) {
                        var div = $("<div><h3>" + parentCluster + " AS " + ascore + "</h3></div>");
                        $("#searchResults").append(div);
                    } else {
                        $("#searchResults").empty();
                    }
                    if (matches.length > 0)
                        $("#searchResults").append(table).show();
                    $("#searchUi").hide();
                    progress.stop();
                };

                var netInfoFn = function(network) {
                   processFn(network, data.matches);
                   var dicedClusters = Object.keys(data.diced_matches);
                   if (dicedClusters.length > 0) {
                       for (var i = 0; i < dicedClusters.length; i++) {
                           var parentCluster = dicedClusters[i];
                           var ascores = Object.keys(data.diced_matches[parentCluster]);
                           for (var ai = 0; ai < ascores.length; ai++) {
                               var ascore = ascores[ai];
                               var matches = data.diced_matches[parentCluster][ascore];
                               //console.log(matches);
                               //for (var mi = 0; mi < matches.length; mi++) {
                                   processFn(network, matches, parentCluster, ascore);
                               //}
                           }
                       }
                   }
                };

                getNetInfo(version, netInfoFn);
//                $.get("getdata.php", {a: "netinfo", v: version}, function (netDataStr) {
//                    var netData = parseNetworkJson(netDataStr);
//                    var network;
//                    if (netData !== false) {
//                        if (netData.valid) {
//                            network = new Network("", netData);
//                        }
//                    }
//                    if (network) {
//                        processFn(network, data.matches);
//                        var dicedClusters = Object.keys(data.diced_matches);
//                        if (dicedClusters.length > 0) {
//                            for (var i = 0; i < dicedClusters.length; i++) {
//                                var parentCluster = dicedClusters[i];
//                                var ascores = Object.keys(data.diced_matches[parentCluster]);
//                                for (var ai = 0; ai < ascores.length; ai++) {
//                                    var ascore = ascores[ai];
//                                    var matches = data.diced_matches[parentCluster][ascore];
//                                    console.log(matches);
//                                    //for (var mi = 0; mi < matches.length; mi++) {
//                                        processFn(network, matches, parentCluster, ascore);
//                                    //}
//                                }
//                            }
//                        }
//                    }
//                });
            }
        });
    };
    var searchIdFn = function() {
        var idVal = $("#searchId").val();
        var version = getVersion();
        $.post(searchApp, {t: "id", query: idVal, v: version}, function(dataStr) {
            var data = JSON.parse(dataStr);
            console.log(data.status);
            if (data.status !== true) {
                $("#searchIdErrorMsg").text(data.message).show();
            } else {
                if (typeof data.cluster_id === "object") {
                    var addClusterTableFn = function(network) {
                        var table = $('<table class="table table-sm"></table>');
        		        table.append('<thead><tr><th>Cluster</th><th>Alignment Score</th></thead>');
        		        var body = $('<tbody>');
                		table.append(body);
                        var ascores = Object.keys(data.cluster_id);
                        for (var i = 0; i < ascores.length; i++) {
                            var ascore = ascores[i];
                            var clusterId = data.cluster_id[ascore];
                            var netName = typeof network !== 'undefined' ? network.getNetworkMapName(clusterId) : clusterId;
                            body.append('<tr><td><a href="explore.html?v=' + version + '&id=' + clusterId + "&as=" + ascore + '">' + netName + '</a></td><td>' + ascore + '</td></tr>');
                        }
                        $("#searchResults").empty().append(table).show();
                        $("#searchUi").hide();
                    };
                    getNetInfo(version, addClusterTableFn);
                } else {
                    window.location.href = "explore.html?v=" + version + "&id=" + data.cluster_id;
                }
            }
        });
    };
    var getSearchTaxType = function() {
        return $("#searchTaxTypeGenus").prop("checked") ? "genus" : ($("#searchTaxTypeFamily").prop("checked") ? "family" : "species");
    };
    var searchTaxFn = function() {
        var termVal = $("#searchTaxTerm").val();
        var termType = getSearchTaxType();
        var version = getVersion();
        var progress = new Progress($("#progressLoader"));
        progress.start();
        $.post(searchApp, {t: "tax", query: termVal, type: termType, v: version}, function(dataStr) {
            var data = JSON.parse(dataStr);
            if (data.status !== true) {
                $("#searchTaxTermErrorMsg").text(data.message).show();
            } else {
                var processFn = function(network, matches, isDiced = false) {
                    var table = $('<table class="table table-sm w-50"></table>');
                    if (isDiced)
        		        table.append('<thead><tr><th>Cluster</th><th>Alignment Score</th><th>Number of Hits</th></thead>');
                    else
        		        table.append('<thead><tr><th>Cluster</th><th>Number of Hits</th></thead>');
    		        var body = $('<tbody>');
            		table.append(body);
                    for (var i = 0; i < matches.length; i++) {
                        var netName = typeof network !== 'undefined' ? network.getNetworkMapName(matches[i][0]) : matches[i][0];
                        var ascoreParm = isDiced ? "&as=" + matches[i][1] : "";
                        if (netName) {
                            var tr = $('<tr>');
                            tr.append($('<td><a href="explore.html?v=' + version + '&id=' + matches[i][0] + ascoreParm + '">' + netName + '</a></td>'));
                            if (isDiced)
                                tr.append($('<td>' + matches[i][1] + '</td>'));
                            tr.append($('<td>' + matches[i][isDiced ? 2 : 1] + '</td>'));
                            body.append(tr);
                        }
                    }
                    if (!isDiced)
                        $("#searchResults").empty();
                    $("#searchResults").append(table).show();
                    $("#searchUi").hide();
                };

                $.get("getdata.php", {a: "netinfo", v: version}, function (netDataStr) {
                    var netData = parseNetworkJson(netDataStr);
                    var network;
                    if (netData !== false) {
                        if (netData.valid) {
                            network = new Network("", netData);
                        }
                    }
                    processFn(network, data.matches);
                    if (typeof data.diced_matches !== "undefined" && data.diced_matches.length > 0) {
                        processFn(network, data.diced_matches, true);
                    }
                    progress.stop();
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
        prefetch: searchApp + '?t=tax-prefetch',
        remote: {
            url: searchApp + '?t=tax-auto&query=%QUERY',
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

