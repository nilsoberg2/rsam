
// Extend the jQuery API for data available buttons
$(document).ready(function () {
    $(".copy-btn").click(function(e) {
        var id = $(this).data("id");
        if (id)
            copyToClipboard(id);
    });

    $.fn.extend({
        enableDataAvailableButton: function () {
            $(this).removeClass("btn-outline-secondary").addClass("btn-secondary").removeClass("disabled");
        },
    });
});


////////////////////////////////////////////////////////////////////////////////////////////////////
// APP CLASS FOR POPULATING THE UI
// 
function App() {
}


////////////////////////////////////////////////////////////////////////////////////////////////////
// ERROR HANDLING/UI
//
App.prototype.responseError = function(msg) {
    $("#loadError").text(msg).show();
}
App.prototype.invalidNetworkJsonError = function() {
    $("#loadError").text("An application error occurred (invalid data in response).").show();
}
App.prototype.getDownloadButton = function (fileType) {
    return '<a href="data/' + this.network.Id + '/' + fileType + '"><button class="btn btn-primary btn-sm">Download</button></a>';
}
App.prototype.getDownloadSize = function (fileType) {
    //TODO: implement this
    return '&lt; 1 MB';
}


////////////////////////////////////////////////////////////////////////////////////////////////////
// INITIALIZE/STARTUP
//
App.prototype.init = function(network) {
    this.network = network;
    var hasSubgroups = network.getSubgroups().length > 0;
    var hasRegions = network.getRegions().length > 0;
    var isLeaf = !hasSubgroups && !hasRegions;

    this.progress = new Progress($("#progressLoader"));
    this.progress.start();

    this.setPageHeaders(isLeaf);
    this.setClusterImage();
    this.addBreadcrumb();
    this.addTigrFamilies();
    this.checkForKegg();

    // Terminal endpoint
    if (isLeaf) {
        this.addClusterSize();
        this.addSwissProtFunctions();
        this.addPdb();
        this.addDisplayFeatures();
        this.addDownloadFeatures();
        $("#dataAvailable").show();
        $("#submitAnnoLink").attr("href", $("#submitAnnoLink").attr("href") + "?id=" + this.network.Id);
        $("#displayFeatures").show();
        this.addSunburstFeature();
        this.progress.stop(); 

    // Still more stuff to zoom in to
    } else {
        this.addClusterNumbers($("#clusterNums"));
        var clusterTableDiv = $('<div id="clusterTable"></div>');
        this.addSubgroupTable(clusterTableDiv);
        $("#subgroupTable").show().append(clusterTableDiv);

        $(".row-clickable tr")
            .mouseover(function () {
                $("#cluster-region-" + $(this).data("node-id")).mouseover();
            })
            .mouseout(function () {
                $("#cluster-region-" + $(this).data("node-id")).mouseout();
            })
            .click(function () {
                var id = $(this).data("node-id");
                goToUrlFn(id);
                //alert($(this).data("node-id"));
            });
    }
}


////////////////////////////////////////////////////////////////////////////////////////////////////
// PAGE TEXT ELEMENTS
//
App.prototype.setPageHeaders = function (isLeafPage) {
    document.title = this.network.getPageTitle();
    $("#familyTitle").text(document.title);
    $("#clusterDesc").text(this.network.getDescription());
    var headerText = isLeafPage ? " Data" : " Subgroups and Clusters";
    $("#clusterTableContainer").append("<h2>" + this.network.getName() + headerText + "</h2>");
}
App.prototype.addClusterSize = function () {
    var size = this.network.getSizes();
    if (size === false)
        return;
    $("#clusterSize").append('UniProt: <b>' + parseInt(size.uniprot).toLocaleString() + '</b>, UniRef90: <b>' + parseInt(size.uniref90).toLocaleString() + '</b>, UniRef50: <b>' + parseInt(size.uniref50).toLocaleString() + '</b>');
    $("#clusterSizeContainer").show();
}


////////////////////////////////////////////////////////////////////////////////////////////////////
// UI ELEMENTS (DOWNLOAD, DISPLAY)
//
App.prototype.addDisplayFeatures = function () {
    var feat = this.network.getDisplayFeatures();
    var that = this;
    for (var i = 0; i < feat.length; i++) {
        if (feat[i] == "weblogo") {
            //TESTING/DEBUGGING:
            //var img = $('<img src="data/weblogo.png" alt="WebLogo for ' + this.network.Id + '" class="display-img-width">');
            var img = $('<img src="data/' + this.network.Id + '/weblogo.png" alt="WebLogo for ' + this.network.Id + '" class="display-img-width">');
            $("#weblogo").append(img);
            $("#downloadWeblogoImage").click(function (e) { e.preventDefault(); window.location.href = "data/" + that.network.Id + "/weblogo.png"; });
            $("#weblogoContainer").show();
        } else if (feat[i] == "length_histogram") {
            //TESTING/DEBUGGING:
            //var img = $('<img src="data/length_histogram.png" alt="Length histogram for ' + this.network.Id + '" class="display-img-width">');
            var img = $('<img src="data/' + this.network.Id + '/length_histogram_sm.png" alt="Length histogram for ' + this.network.Id + '" class="display-img-width">');
            $("#fullLengthHistogram").append(img);
            $("#downloadFullLenHistoImage").click(function (e) { e.preventDefault(); window.location.href = "data/" + that.network.Id + "/length_histogram_lg.png"; });
            //TESTING/DEBUGGING:
            //var img = $('<img src="data/length_histogram.png" alt="Length histogram for ' + this.network.Id + '" class="display-img-width">');
            var img = $('<img src="data/' + this.network.Id + '/length_histogram_filtered_sm.png" alt="Length histogram for ' + this.network.Id + '" class="display-img-width">');
            $("#filteredLengthHistogram").append(img);
            $("#downloadFiltLenHistoImage").click(function (e) { e.preventDefault(); window.location.href = "data/" + that.network.Id + "/length_histogram_filtered_lg.png"; });
            $("#lengthHistogramContainer").show();
        }
    }
}
App.prototype.addDownloadFeatures = function () {
    var feat = this.network.getDownloadFeatures();
    if (feat.length == 0)
        return false;

    var table = $('<table class="table table-sm text-center w-auto"></table>');
    table.append('<thead><tr><th>Download</th><th>File Type</th><th>Size</th></thead>');
    var body = $('<tbody>');
    table.append(body);

    for (var i = 0; i < feat.length; i++) {
        //"downloads": ["weblogo", "msa", "hmm", "id_fasta", "misc"]
        if (feat[i] == "gnn") {
        } else if (feat[i] == "weblogo") {
            body.append('<tr><td>' + this.getDownloadButton(feat[i] + ".png") + '</td><td>WebLogo for Length-Filtered Node Sequences</td><td>' + this.getDownloadSize(feat[i]) + '</td></tr>');
        } else if (feat[i] == "msa") {
            body.append('<tr><td>' + this.getDownloadButton(feat[i] + ".afa") + '</td><td>MSA for Length-Filtered Node Sequences</td><td>' + this.getDownloadSize(feat[i]) + '</td></tr>');
        } else if (feat[i] == "hmm") {
            body.append('<tr><td>' + this.getDownloadButton(feat[i] + ".hmm") + '</td><td>HMM for Length-Filtered Node Sequences</td><td>' + this.getDownloadSize(feat[i]) + '</td></tr>');
        } else if (feat[i] == "id_fasta") {
            var t = [
                { "key": "uniprot.txt", "desc": "UniProt ID list" },
                { "key": "uniref90.txt", "desc": "UniRef90 ID list" },
                { "key": "uniref50.txt", "desc": "UniRef50 ID list" },
                { "key": "uniprot.fasta", "desc": "UniProt FASTA file" },
                { "key": "uniref90.fasta", "desc": "UniRef90 FASTA file" },
                { "key": "uniref50.fasta", "desc": "UniRef50 FASTA file" }
            ];

            table.append('<tbody><tr><td colspan="3"><b>ID Lists and FASTA Files</b></td></tr></tbody>');
            body = $('<tbody>');
            table.append(body);

            for (var k = 0; k < t.length; k++) {
                body.append('<tr><td>' + this.getDownloadButton(t[k].key) + '</td><td>' + t[k].desc + '</td><td>' + this.getDownloadSize(t[k].key) + '</td></tr>');
            }
        } else if (feat[i] == "misc") {
            var t = [
                //{ "key": "cluster_size", "desc": "Cluster sizes" },
                { "key": "swissprot.txt", "desc": "SwissProt annotations within cluster" },
                //{ "key": "sp_singletons", "desc": "SwissProt annotations by singletons" }
            ];

            table.append('<tbody><tr><td colspan="3"><b>Miscellaneous Files</b></td></tr></tbody>');
            body = $('<tbody>');
            table.append(body);

            for (var k = 0; k < t.length; k++) {
                body.append('<tr><td>' + this.getDownloadButton(t[k].key) + '</td><td>' + t[k].desc + '</td><td>' + this.getDownloadSize(t[k].key) + '</td></tr>');
            }
        }
    }

    $("#downloads").append(table);
    $("#downloadContainer").show();
}


////////////////////////////////////////////////////////////////////////////////////////////////////
// PAGE IMAGE ELEMENTS
// 
App.prototype.setClusterImage = function () {
    var img = $("#clusterImg");
    var fileName = this.network.getImage();
    var that = this;
    if (Array.isArray(fileName))
        ;//TODO:
    else
        img
            .attr("src", "data/" + this.network.Id + "/" + fileName + "_sm.png")
            .on("load", function () { that.addClusterHotspots(img); that.progress.stop(); });
    $("#downloadClusterImage").click(function (e) {
        e.preventDefault();
        window.location.href = "data/" + that.network.Id + "/" + fileName + "_lg.png";
    });
}
// This should be called on the image
App.prototype.addClusterHotspots = function (img) {
    var parent = img.parent();
    var w = img.width() / 100;
    var h = img.height() / 100;
    var imgmap = $('<map name="clusterHotspotMap" id="clusterHotspotMap"></map>');
    $.each(this.network.getRegions(), function (i, data) {
        var coords = [data.coords[0] * w, data.coords[1] * h, data.coords[2] * w, data.coords[3] * h];
        var coordStr = coords.join(",");
        var shape = $('<area shape="rect" coords="' + coordStr + '" id="cluster-region-' + data.id + '" href="' + getUrlFn(data.id) + '">');
        shape
            .click(function () {
                goToUrlFn(data.id);
            })
            .mouseover(function () {
                $("#cluster-num-text-" + data.id).css({ color: "red", "text-decoration": "underline" });
            }).mouseout(function () {
                $("#cluster-num-text-" + data.id).css({ color: "inherit", "text-decoration": "inherit" });
            });
        imgmap.append(shape);
    });
    parent.append(imgmap);
    img.attr("usemap", "#clusterHotspotMap");
    img.maphilight();
    //imgmap.imageMapResize();
}
App.prototype.addClusterNumbers = function (parent) {
    var pw = parent.width();
    $.each(this.network.getRegions(), function (i, data) {
        var obj = $('<span id="cluster-num-text-' + data.id + '">' + data.name + "</span>");
        parent.append(obj);
        // Calculate the width of the text to properly align text for small clusters
        var offset = (obj.width() / pw * 100 / 2);
        var pos = data.coords[0] + (data.coords[2] - data.coords[0]) / 2 - offset;
        obj.css({
            position: "absolute",
            top: "0px",
            left: pos + "%"
        });
    });
}


////////////////////////////////////////////////////////////////////////////////////////////////////
// PAGE NAVIGATION ELEMENTS
//
App.prototype.addBreadcrumb = function() {
    var nav = $("#exploreBreadcrumb");
    var parts = this.network.Id.split("-");
    if (parts.length > 1) {
        var ol = $('<ol class="breadcrumb"><li class="breadcrumb-item" aria-current="page"><a href="?">Explore</a></li>');
        for (var i = 1; i < parts.length; i++) {
            var item = "";
            if (i == parts.length - 1) {
                item = '<li class="breadcrumb-item active" aria-current="page">' + this.network.getName() + '</li>';
            } else {
                var parentId = parts.slice(0, i + 1).join("-");
                item = '<li class="breadcrumb-item"><a href="?id=' + parentId + '">';
                var parentNet = this.network.getNetworkMapName(parentId);
                if (typeof parentNet !== "undefined")
                    item += parentNet;
                else
                    item += parts[i];
                item += '</li>';
            }
            ol.append($(item));
        }
        nav.append(ol);
        nav.show();
    }
}


////////////////////////////////////////////////////////////////////////////////////////////////////
// FAMILY/ANNOTATION ELEMENTS
//
App.prototype.addTigrFamilies  = function () {
    var tigr = this.network.getTigr();
    if (tigr.length == 0)
        return;
    var table = $('<table class="table table-sm w-auto"></table>');
    var head = $('<thead><tr><th>TIGR ID</th><th>TIGR Description</th></tr></thead>');
    var body = $('<tbody></tbody>');
    for (var i = 0; i < tigr.length; i++) {
        body.append('<tr><td>' + tigr[i].id + '</td><td>' + tigr[i].description + '</td></tr>');
    }
    table.append(head).append(body);
    if (tigr.length > 0) {
        $("#tigrFams").append(table);
        $("#tigrFamContainer").show();
        $("#dataAvailableTigr").enableDataAvailableButton();
    }
}
App.prototype.checkForKegg = function () {
    if (this.network.getKeggCount() == 0)
        return;
    var that = this;
    //$("#keggList").append("<div>This cluster contains " + parseInt(this.network.getKeggCount()).toLocaleString() + " KEGG-annotated sequences.</div>");
    $("#dataAvailableKegg").click(function () {
        that.network.getKeggIds(function (id) {
            $("#keggIdList").append('<a href="https://www.genome.jp/dbget-bin/www_bget?' + id + '">' + id + '</a><br>');
            $("#keggIdListClip").append(id + "\n");
        }, function () {
            $("#keggIdModal").modal();
        });
    });
    $("#otherAnnoContainer").show();
    $("#dataAvailableKegg").enableDataAvailableButton();
}
App.prototype.addSwissProtFunctions = function () {
    var list = this.network.getSwissProtFunctions();
    if (list === false || list.length == 0)
        return;
    var ecodes = this.network.getEnzymeCodes();
    var ul = $('<ul class="expandable"></ul>');
    for (var i = 0; i < list.length; i++) {
        var parts = list[i][0].split("||");
        var desc = parts[0];
        if (parts.length > 0 && typeof parts[1] !== "undefined") {
            var code = parts[1];
            if (code) {
                var codeDesc = ecodes[code];
                var linkCode = '<a href="https://enzyme.expasy.org/EC/' + code + '">' + code + '</a>';
                if (typeof codeDesc !== "undefined")
                    codeDesc = '<span data-toggle="tooltip" title="' + codeDesc + '">' + linkCode + '</span>';
                else
                    codeDesc = linkCode;
                desc += " (" + codeDesc + ")";
            }
        }
        var spItemIds = list[i][1].split(",").map(x => '<a href="https://www.uniprot.org/uniprot/'+x+'">'+x+'</a>').join("<br>\n");
        ul.append('<li data-toggle="collapse" data-target="#spListItem' + i + '">' + desc + '<div class="collapse sp-list-item" id="spListItem' + i + '">' + spItemIds + '</div>' + '</li>');
        spItemIds = "\t" + list[i][1].split(",").join("\n\t") + "\n";
        $("#spModalIdListClip").append(desc + "\n" + spItemIds);
    }
    $("#spFunctions").append(ul);
    $("#spFunctionContainer").show();
    $("#spFunctions ul li span").css({ "font-style": "italic" });
    $("#dataAvailableSp").click(function() { $("#spModal").modal(); }).enableDataAvailableButton();
}
App.prototype.addPdb = function () {
    var pdb = this.network.getPdb();
    if (pdb.length == 0)
        return;
    for (var i = 0; i < pdb.length; i++) {
        var ids = pdb[i][0].split(',');
        for (var j = 0; j < ids.length; j++) {
            var id = ids[j];
            $("#pdbIdList").append('<a href="https://www.rcsb.org/structure/' + id + '">' + id + '</a> (' + pdb[i][1] +  ')<br>');
            $("#pdbIdListClip").append(id + "\t" + pdb[i][1] + "\n");
        }
    }
    $("#pdbIdModal").modal();
    $("#dataAvailablePdb").click(function() { $("#pdbModal").modal(); }).enableDataAvailableButton();
}


App.prototype.addSubgroupTable = function (div) {
    var table = $('<table class="table table-hover w-auto"></table>');

    var that = this;

    // If there are cleanly-defined sub-clusters, then the 'regions' property will be present.
    if (typeof this.network.getRegions() !== "undefined") {
        var headHtml = '<thead><tr class="text-center"><th>Cluster</th><th>ID Cluster Number</th>';
        if (this.network.Id != "fullnetwork") //TODO: HACK
            headHtml += '<th>SFLD Subgroup</th>';
        headHtml += '<th>UniProt</th><th>UniRef90</th><th>UniRef50</th></tr></thead>';
        var head = table.append(headHtml);
        var body = table.append('<tbody class="row-clickable text-center"></tbody>');
        $.each(this.network.getRegions(), function (i, data) {
            var row = $('<tr data-node-id="' + data.id + '"></tr>');
            var size = that.network.getSizes(data.id);
            var sfldDesc = "";
            var sfldIds = that.network.getSfldIds(data.id);
            for (var i = 0; i < sfldIds.length; i++) {
                var sfldId = sfldIds[i];
                if (sfldDesc.length)
                    sfldDesc += '; ';
                sfldDesc += "<span style=\"color: " + that.network.getSfldColor(sfldId) + ";\">" + that.network.getSfldDesc(sfldId) + " (" + sfldId + ")</span>";
            }
            var rowHtml = "<td>" + data.name + "</td><td>" + data.number + "</td>";
            if (that.network.Id != "fullnetwork") //TODO: HACK
                rowHtml += "<td>" + sfldDesc + "</td>";
            rowHtml += "<td>" + parseInt(size.uniprot).toLocaleString() + "</td><td>" + parseInt(size.uniref90).toLocaleString() + "</td><td>" + parseInt(size.uniref50).toLocaleString() + "</td>";
            row.append(rowHtml);
            body.append(row);
        });
    } else if (typeof this.network.getSubgroups() !== "undefined") {
        var head = table.append('<thead><tr><th>Cluster</th><th>SFLD Number</th><th>Subgroup</th></tr></thead>');
        var body = table.append('<tbody class="row-clickable text-center"></tbody>');
        $.each(this.network.getSubgroups(), function (i, data) {
            var descColor = typeof data.color !== "undefined" ? ' style="color: '+data.color+'; font-weight: bold"' : "";
            var descCol = "<td" + descColor + ">" + data.desc + "</td>";
            var row = $('<tr data-node-id="' + data.id + '"></tr>');
            row.append("<td>" + data.name + "</td><td>" + data.sfld + "</td>" + descCol);
            body.append(row);
        });
    }
    div.append(table);
}


////////////////////////////////////////////////////////////////////////////////////////////////////
// SUNBURST
//
App.prototype.showSunburst = function() {
}


App.prototype.addSunburstFeature = function() {
    var that = this;
    var Colors = getSunburstColorFn();

    $("#dataAvailableSunburst").click(function() {
        var progress = new Progress($("#sunburstProgressLoader"));
        progress.start();
        $("#sunburstModal").modal();
        $.ajax({
            dataType: "json",
            url: "getdata.php",
            data: {cid: that.network.Id, a: "tax"},
            success: function(treeData) {
                if (typeof(treeData.valid) !== "undefined" && treeData.valid === "false") {
                    //TODO: handle error
                    alert(treeData.message);
                } else {
                    Sunburst()
                        .width(600)
                        .height(600)
                        .data(treeData)
                        .label("node")
                        .size("numSpecies")
                        .color(Colors)
                        //.color((d, parent) => color(parent ? parent.data.name : null))
                        //.tooltipContent((d, node) => `Size: <i>${node.value}</i>`)
                        (document.getElementById("sunburstChart"));
                    progress.stop();
                }
            }
        });
    }).enableDataAvailableButton();
}


////////////////////////////////////////////////////////////////////////////////////////////////////
// UTILITY FUNCTIONS
//
function getPageClusterId() {
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
}
function getUrlFn(id) {
    return "?id=" + id;
}
function goToUrlFn(id) {
    window.location = getUrlFn(id);
}
function copyToClipboard(id) {
    $("#"+id).show().select();
    document.execCommand("copy");
    $("#"+id).hide();
}
const sleep = (milliseconds) => {
    return new Promise(resolve => setTimeout(resolve, milliseconds))
}


////////////////////////////////////////////////////////////////////////////////////////////////////
// PROGRESS UI
//
function Progress(progressDiv) {
    this.progress = progressDiv;
}
Progress.prototype.start = function() {
    this.progress.show();
}
Progress.prototype.stop = function() {
    this.progress.hide();
}


