
const DEFAULT_VERSION = "2.1";

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

function initApp(version) {
    var requestData = getPageClusterId();
    var requestId = "", alignmentScore = "";
    if (!requestData.network_id)
        requestId = "fullnetwork";
    else
        requestId = requestData.network_id;
    if (requestData.alignment_score)
        alignmentScore = requestData.alignment_score;
    version = requestData.version;
    var app = new App(version, alignmentScore);
    var args = {a: "cluster", cid: requestId, v: version};
    if (alignmentScore)
        args["as"] = alignmentScore;
    $.get("getdata.php", args, function (dataStr) {
        var data = parseNetworkJson(dataStr);
        if (data !== false) {
            if (data.valid) {
                var network = new Network(requestId, data);
                app.init(network);
            } else {
                app.responseError(data.message);
            }
        } else {
            app.invalidNetworkJsonError(dataStr);
        }
    });
}


////////////////////////////////////////////////////////////////////////////////////////////////////
// APP CLASS FOR POPULATING THE UI
// 
function App(version) {
    this.version = version;
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
    return '<a href="' + this.getDownloadUrl(fileType) + '"><button class="btn btn-primary btn-sm">Download</button></a>';
}
App.prototype.getDownloadSize = function (fileType) {
    //TODO: implement this
    return "";//'&lt; 1 MB';
}


////////////////////////////////////////////////////////////////////////////////////////////////////
// INITIALIZE/STARTUP
//
App.prototype.init = function(network) {
    this.network = network;
    var hasSubgroups = network.getSubgroups().length > 0;
    var hasRegions = network.getRegions().length > 0;
    var hasDicedChildren = network.getDicedChildren().length > 0;
    var isLeaf = !hasSubgroups && !hasRegions && !hasDicedChildren;

    this.dataDir = network.getDataDir();
    this.alignmentScore = network.getAlignmentScore();

    this.progress = new Progress($("#progressLoader"));
    this.progress.start();

    this.setPageHeaders();
    this.addBreadcrumb();
    if (this.network.getAltSsns().length > 0) {
        this.initAltSsn();
        var dlDivId = "downloads";
        var hideTabStuff = false;
        if (this.alignmentScore) {
            this.initTabPages();
            dlDivId = "childDownload";
            hideTabStuff = true;
            $("#altSsnSecondary").show();
        } else {
            $("#downloadContainer").show();
            $("#altSsnPrimary").show();
            $(".alt-cluster-ascores").text(this.network.getAltSsns().join(", "));
            $(".alt-cluster-id").text(this.network.getName());
            $(".alt-cluster-default-as").text(network.getDefaultAlignmentScore());
        }
        this.addDownloadFeatures(dlDivId, hideTabStuff);
    } else {
        this.initStandard(isLeaf);
        if (this.network.Id != "fullnetwork")
            this.initLeafData();
        // Still more stuff to zoom in to
        if (!isLeaf)
            this.initChildren();
        if (this.network.Id != "fullnetwork") {
            this.addDownloadFeatures("downloads")
            $("#downloadContainer").show();
        }
    }

    applyRowClickableFn(this.version);

    $(".hmm-logo").click(function(evt) {
        var parm = $(this).data("logo");
        var windowSize = ["width=1500,height=600"];
        var url = "view_logo.php?" + parm;
        var theWindow = window.open(url, "", windowSize);
        evt.preventDefault();
    });
}
App.prototype.initAltSsn = function() {
    this.addClusterSize();
    this.setClusterImage(function() {});
    var altDiv = $("#altSsn");
    this.addAltSsns(altDiv);
    $("#submitAnnoLink").attr("href", $("#submitAnnoLink").attr("href") + "?id=" + this.network.Id);
        $("#dataAvailable").show();
}
App.prototype.initStandard = function(isLeaf) {
    var that = this;
    var addClusterNumbersFn = function() {
        if (!isLeaf)
            that.addClusterNumbers($("#clusterNums"));
    };
    this.setClusterImage(addClusterNumbersFn);
    if (this.network.Id != "fullnetwork") {
        this.addTigrFamilies();
        this.checkForKegg();
    }
}
App.prototype.initLeafData = function() {
    var hasData = this.addDisplayFeatures();
    this.addClusterSize();
    if (hasData) {
        this.addSwissProtFunctions();
        this.addPdb();
        $("#displayFeatures").show();
        $("#dataAvailable").show();
        this.addSunburstFeature();
    }
    $("#submitAnnoLink").attr("href", $("#submitAnnoLink").attr("href") + "?id=" + this.network.Id);
}
App.prototype.initTabPages = function() {
    var kids = this.network.getDicedChildren();
    if (!kids.length)
        return;

    var that = this;
    //TODO: var consDiv = $("<div></div>");

    var getDownloadFn = function(type, networkId, altText = "") {
        var download = $('<span class="download-btn link-dl">' + altText + '</span>');
        download.append('<i class="fas fa-download" data-toggle="tooltip" title="Download ' + altText + '"></i>');
        download.click(function (e) { e.preventDefault(); window.location.href = that.getDownloadUrl(type, networkId); });
        var downloadDiv = $('<div class="float-right"></div>');
        downloadDiv.append(download);
        return downloadDiv;
    };
    var getSsnFn = function(networkId) {
        var clusterParms = 'id=' + networkId;
        if (that.alignmentScore)
            clusterParms += '&as=' + that.alignmentScore;
        if (that.version)
            clusterParms += '&v=' + that.version;
        var div = $('<div class="float-right"></div>');
        var link = $('<a class="link-dl" href="explore.html?' + clusterParms + '"></a>');
        link.append('<span class="download-btn mr-4" data-toggle="tooltip" title="View Cluster Page">Cluster <img src="img/cytossn.png"></span>');
        div.append(link);
        return div;
    };

    var getSizeDivFn = function(size) {
        return $('<div><span class="diced-cluster-size">Number of IDs: UniProt: <b>' + size.uniprot + '</b>, UniRef90: <b>' + size.uniref90 + '</b>, UniRef50: <b>' + size.uniref50 + '</b></span></div>');
    };
    
    var mkHistoFn = function(dataDir, networkId, size, networkName) {
        var div = $('<div class="w-50"><h4>' + networkName + '</h4></div>');
        div.append(getSizeDivFn(size));
        //TESTING/DEBUGGING:
        //var img = $('<img src="data/length_histogram.png" alt="Length histogram for ' + that.network.Id + '" class="display-img-width">');
        div.append(getDownloadFn("hist", networkId, "PNG "));
        div.append(getSsnFn(networkId));
        var img = $('<div><img src="' + dataDir + '/length_histogram_sm.png" alt="Length histogram for ' + networkName + '" class="display-img-width lazy"></div>');
        div.append(img);
        div.append(getDownloadFn("hist_ur50", networkId, "PNG "));
        img = $('<div><img src="' + dataDir + '/length_histogram_uniref50_sm.png" alt="Length histogram histogram for ' + networkName + ' with UniRef50 sequences" class="display-img-width lazy"></div>');
        div.append(img);
        return div;
    };

    var mkLogoFn = function(dataDir, networkId, size, networkName) {
        var div = $('<div class="w-50"><h4>' + networkName + '</h4></div>');
        //TESTING/DEBUGGING:
        //var img = $('<img src="data/weblogo.png" alt="WebLogo for ' + that.network.Id + '" class="display-img-width">');
        var img = $('<img src="' + dataDir + '/weblogo.png" alt="WebLogo for ' + networkName + '" class="display-img-width lazy">');
        div.append(getSizeDivFn(size));
        div.append(getDownloadFn("weblogo", networkId, "PNG "));
        div.append(getDownloadFn("msa", networkId, "MSA ").addClass("mr-4"));
        div.append(getSsnFn(networkId));
        div.append(img);
        return div;
    };

    var mkHmmFn = function(dataDir, networkId, size, networkName) {
        var div = $('<div><h4>' + networkName + '</h4></div>');
        var img = $('<img src="' + dataDir + '/hmm.png" alt="HMM Skylign for ' + networkName + '" class="display-img-width lazy">');
        div.append(getSizeDivFn(size));
        div.append(getDownloadFn("hmm", networkId, "HMM "));
        div.append(getDownloadFn("hmmpng", networkId, "PNG ").addClass("mr-4"));
        var logoParms = 'cid=' + networkId;
        if (that.alignmentScore)
            logoParms += '&as=' + that.alignmentScore;
        if (that.version)
            logoParms += '&v=' + that.version;
        var logoDiv = $('<div class="float-right"></div>');
        logoDiv.append('<span class="download-btn mr-4 hmm-logo link-dl" data-logo="' + logoParms + '">Skylign <i class="fas fa-eye" data-toggle="tooltip" title="View HMM in SkyLign"></i></span>');
        div.append(logoDiv);
        div.append(getSsnFn(networkId));
        div.append(img);
        return div;
    };

    var mkTable = function(data) {
        var table = $('<table class="table table-sm text-center w-auto"></table>');
        var body = $('<tbody></tbody>');
        for (var i = 0; i < data.length; i++) {
            body.append('<tr><td>' + that.getDownloadButton(data[i][0]) + '</td><td>' + data[i][1] + '</td></tr>');
                    //<td>'
                    //+ that.getDownloadSize(data[i][0])
                    //+ '</td></tr>');
        }
        table.append(body);
        return table;
    };

    var consResTypes = this.network.getConsensusResidues();
    if (consResTypes.length == 0) {
        $("#childConsResListItem").hide();
        $("#childConsRes").hide();
    } else {
        var table = $('<table class="table table-sm text-center w-auto"></table>');
        for (var ri = 0; ri < consResTypes.length; ri++) {
            var consRes = consResTypes[ri];
            //var bodyRes = $('<tbody></tbody>');
            //bodyRes.append('<tr><td colspan="3" class="file-header-row">' + consRes + '</td></tr>');
            //table.append(bodyRes);
            var body = $('<tbody></tbody>');
            var res = consRes.toLowerCase();
            //body.append('<tr><td>' + this.getDownloadButton("crpo"+res) + '</td><td>Consensus residue position summary table</td><td>&lt;1 MB</td></tr>');
            body.append('<tr><td>' + this.getDownloadButton("crpe"+res) + '</td><td>Consensus residue percentage summary table</td><td>&lt;1 MB</td></tr>');
            //body.append('<tr><td>' + this.getDownloadButton("crid"+res) + '</td><td>ID lists grouped by consensus residue percentage and number of residues in cluster</td><td>&lt;1 MB</td></tr>');
            table.append(body);
        }
        $("#childConsRes").append(table);
    }

    var histDiv = $("<div><h3>Length Histograms</h3></div>");
    histDiv.append(mkTable([["hist_up.zip", "Length Histograms for all clusters, UniProt sequences"],
                            ["hist_ur50.zip", "Length Histograms for all clusters, UniRef50 sequences"]]));
    var logoDiv = $("<div><h3>WebLogos</h3></div>");
    logoDiv.append(mkTable([["weblogo.zip", "WebLogos for all clusters"],
                            ["msa.zip", "Multiple Sequence Alignments (MSAs) for all clusters"]]));
    var hmmDiv = $("<div><h3>HMMs</h3></div>");
    hmmDiv.append(mkTable([["hmm.zip", "HMMs for all clusters"]]));
    for (var i = 0; i < kids.length; i++) {
        var kidId = kids[i].id;
        //var kidSize = {uniprot: 0, uniref50: 0, uniref90: 0};
        var kidSizeRaw = kids[i].size;
        var kidSize = {uniprot: commify(kidSizeRaw.uniprot), uniref90: commify(kidSizeRaw.uniref90), uniref50: commify(kidSizeRaw.uniref50)};
        var dataDir = this.dataDir + "/" + kidId;
        var netName = "Mega" + kidId;
        var hist = mkHistoFn(dataDir, kidId, kidSize, netName);
        var logo = mkLogoFn(dataDir, kidId, kidSize, netName);
        var hmm = mkHmmFn(dataDir, kidId, kidSize, netName);
        histDiv.append(hist);
        logoDiv.append(logo);
        hmmDiv.append(hmm);
    }

    $("#childLogo").append(logoDiv);
    $("#childHmm").append(hmmDiv);
    $("#childHisto").append(histDiv);

    $("#childTabs").show();

    /*
    var showFn = function() {
        var clusterTableDiv = $('<div id="clusterTable"></div>');
        that.addSubgroupTable(clusterTableDiv);
        $("#subgroupTable").append(clusterTableDiv);
    };

    var kids = this.network.getDicedChildren();
    if (kids.length > 0) {
        $("#dicingInfo").show();
        var menu = $('<select class="form-control w-25"></select>');
        for (var i = 0; i < kids.length; i++) {
            menu.append($('<option></option>').attr("value", kids[i]).text(kids[i]));
        }
        menu.val("");
        menu.change(function() {
            var id = $(this).val();
            goToUrlFn(id, that.version);
        });
        $("#subgroupTable").append(menu);
        //var showBtnFn = function() {
        //    showFn();
        //    applyRowClickableFn();
        //    $("#showSubgroupTableDiv").hide();
        //};
        //var btn = $('<button class="btn btn-primary btn-sm">Show All Clusters</button>');
        //btn.click(showBtnFn);
        //var btnDiv = $('<div id="showSubgroupTableDiv"></div>').append(btn);
        //$("#subgroupTable").append(btnDiv);
    } else {
        showFn();
    }

    $("#subgroupTable").show();
    */
}
App.prototype.initChildren = function() {
    var that = this;
    var showFn = function() {
        var clusterTableDiv = $('<div id="clusterTable"></div>');
        that.addSubgroupTable(clusterTableDiv);
        $("#subgroupTable").append(clusterTableDiv);
    };

    var kids = this.network.getDicedChildren();
    if (kids.length > 0) {
        $("#dicingInfo").show();
        var menu = $('<select class="form-control w-25"></select>');
        for (var i = 0; i < kids.length; i++) {
            menu.append($('<option></option>').attr("value", kids[i].id).text(kids[i].id));
        }
        menu.val("");
        menu.change(function() {
            var id = $(this).val();
            goToUrlFn(id, that.version);
        });
        $("#subgroupTable").append(menu);
        //var showBtnFn = function() {
        //    showFn();
        //    applyRowClickableFn();
        //    $("#showSubgroupTableDiv").hide();
        //};
        //var btn = $('<button class="btn btn-primary btn-sm">Show All Clusters</button>');
        //btn.click(showBtnFn);
        //var btnDiv = $('<div id="showSubgroupTableDiv"></div>').append(btn);
        //$("#subgroupTable").append(btnDiv);
    } else {
        showFn();
    }

    $("#subgroupTable").show();
}


////////////////////////////////////////////////////////////////////////////////////////////////////
// PAGE TEXT ELEMENTS
//
App.prototype.setPageHeaders = function () {
    document.title = this.network.getPageTitle();
    $("#familyTitle").text(document.title);
    $("#clusterDesc").text(this.network.getDescription());
    var as = this.alignmentScore;
    if (as.length > 0) {
        var hWarn = $('<span class="as-warning"> (Alignment Score ' + as + ')</span>');
        $("#familyTitle").append(hWarn);
    }
}
App.prototype.addClusterSize = function () {
    var size = this.network.getSizes();
    if (size === false)
        return;
    $("#clusterSize").append('UniProt: <b>' + commify(size.uniprot) + '</b>, UniRef90: <b>' + commify(size.uniref90) + '</b>, UniRef50: <b>' + commify(size.uniref50) + '</b>');
    $("#clusterSizeContainer").show();
}


////////////////////////////////////////////////////////////////////////////////////////////////////
// UI ELEMENTS (DOWNLOAD, DISPLAY)
//
App.prototype.addDisplayFeatures = function () {
    var feat = this.network.getDisplayFeatures();
    var that = this;
    var hasData = feat.length > 0;
    for (var i = 0; i < feat.length; i++) {
        if (feat[i] == "weblogo") {
            //TESTING/DEBUGGING:
            //var img = $('<img src="data/weblogo.png" alt="WebLogo for ' + this.network.Id + '" class="display-img-width">');
            var img = $('<img src="' + this.dataDir + '/weblogo.png" alt="WebLogo for ' + this.network.Id + '" class="display-img-width">');
            $("#weblogo").append(img);
            $("#downloadWeblogoImage").click(function (e) { e.preventDefault(); window.location.href = that.getDownloadUrl("weblogo"); });
            $("#weblogoContainer").show();
        } else if (feat[i] == "length_histogram") {
            var mainDiv = $("<div></div>");

            var dlBtn = $('<i class="fas fa-download"></i>');
            var dlDiv = $('<div class="float-right download-btn" data-toggle="tooltip" title="Download high-resolution">PNG </div>').append(dlBtn);
            dlDiv.click(function (e) { e.preventDefault(); window.location.href = that.getDownloadUrl("hist"); });
            
            mainDiv.append('<h5>Length Histogram for All Sequences (UniProt IDs)</h5>');
            // First download
            mainDiv.append(dlDiv);
            mainDiv.append('<div style="clear: both"></div>');
            // Then image
            mainDiv.append('<div></div>')
                .append('<img src="' + this.dataDir + '/length_histogram_sm.png" alt="Length histogram for ' + this.network.Id + '" class="display-img-width">');

            var fileName = "filtered";
            var dlType = "hist_filt";
            if (this.alignmentScore) {
                mainDiv.append('<h5>Length Histogram for Node Sequences (UniRef50 IDs)</h5>');
                fileName = "uniref50";
                dlType = "hist_ur50";
            } else {
                mainDiv.append('<h5>Length-Filtered Histogram for Node Sequences (UniRef50 IDs) &mdash; Used for MSA, WebLogo, and HMM</h5>');
            }
            // First download
            dlBtn = $('<i class="fas fa-download"></i>');
            dlDiv = $('<div class="float-right download-btn" data-toggle="tooltip" title="Download high-resolution">PNG </div>').append(dlBtn);
            dlDiv.click(function (e) { e.preventDefault(); window.location.href = that.getDownloadUrl(dlType); });
            mainDiv.append(dlDiv);
            mainDiv.append('<div style="clear: both"></div>');

            // Then image
            mainDiv.append('<div></div>')
                .append('<img src="' + this.dataDir + '/length_histogram_' + fileName + '_sm.png" alt="Length histogram for ' + this.network.Id + '" class="display-img-width">');
            
            $("#lengthHistogramContainer").append(mainDiv).show();
        }
    }
    return hasData;
}
App.prototype.addDownloadFeatures = function (containerId, hideTabStuff = false) {
    var feat = this.network.getDownloadFeatures();
    if (feat.length == 0)
        return false;

    var isDiced = this.network.getDicedParent().length > 0;

    var table = $('<table class="table table-sm text-center w-auto"></table>');
    table.append('<thead><tr><th>Download</th><th>File Type</th></thead>');//<th>Size</th></thead>');
    var body = $('<tbody>');
    table.append(body);

    for (var i = 0; i < feat.length; i++) {
        //"downloads": ["weblogo", "msa", "hmm", "id_fasta", "misc"]
        if (feat[i] == "gnn") {
        } else if (feat[i] == "ssn") {
            //var parentSsnText = isDiced ? " (for parent cluster)" : "";
            var parentSsnText = "";
            body.append('<tr><td>' + this.getDownloadButton(feat[i] + ".zip") + '</td><td>Sequence Similarity Network' + parentSsnText + '</td></tr>');//<td>' + this.getDownloadSize(feat[i]) + '</td></tr>');
        //} else if (feat[i] == "cons") { // consensus residue
        //    body.append('<tr><td>' + this.getDownloadButton(feat[i] + ".txt") + '</td><td>Consensus Residues</td></tr>');//<td>' + this.getDownloadSize(feat[i]) + '</td></tr>');
        } else if (feat[i] == "weblogo" && !hideTabStuff) {
            body.append('<tr><td>' + this.getDownloadButton(feat[i] + ".png") + '</td><td>WebLogo for Length-Filtered Node Sequences</td></tr>');//<td>' + this.getDownloadSize(feat[i]) + '</td></tr>');
        } else if (feat[i] == "msa" && !hideTabStuff) {
            body.append('<tr><td>' + this.getDownloadButton(feat[i] + ".afa") + '</td><td>MSA for Length-Filtered Node Sequences</td></tr>');//<td>' + this.getDownloadSize(feat[i]) + '</td></tr>');
        } else if (feat[i] == "hmm" && !hideTabStuff) {
            body.append('<tr><td>' + this.getDownloadButton(feat[i] + ".hmm") + '</td><td>HMM for Length-Filtered Node Sequences</td></tr>');//<td>' + this.getDownloadSize(feat[i]) + '</td></tr>');
            if (!hideTabStuff) {
                var logoParms = "";
                var logoParms = 'cid=' + this.network.Id;
                if (this.alignmentScore)
                    logoParms += '&as=' + this.alignmentScore;
                if (this.version)
                    logoParms += '&v=' + this.version;
                var logoBtn = '<button class="btn btn-primary btn-sm hmm-logo" data-logo="' + logoParms + '">View HMM</button>';
                body.append('<tr><td>' + logoBtn + '</td><td>View HMM in SkyLign</td><td></td></tr>');
            }
        } else if (feat[i] == "id_fasta") {
            var t = [
                { "key": "uniprot_id", "desc": "UniProt ID list" },
                { "key": "uniref90_id", "desc": "UniRef90 ID list" },
                { "key": "uniref50_id", "desc": "UniRef50 ID list" },
                { "key": "uniprot_fasta", "desc": "UniProt FASTA file" },
                { "key": "uniref90_fasta", "desc": "UniRef90 FASTA file" },
                { "key": "uniref50_fasta", "desc": "UniRef50 FASTA file" }
            ];

            table.append('<tbody><tr><td colspan="3"><b>ID Lists and FASTA Files</b></td></tr></tbody>');
            body = $('<tbody>');
            table.append(body);

            for (var k = 0; k < t.length; k++) {
                body.append('<tr><td>' + this.getDownloadButton(t[k].key) + '</td><td>' + t[k].desc + '</td></tr>');//<td>' + this.getDownloadSize(t[k].key) + '</td></tr>');
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
                body.append('<tr><td>' + this.getDownloadButton(t[k].key) + '</td><td>' + t[k].desc + '</td></tr>');//<td>' + this.getDownloadSize(t[k].key) + '</td></tr>');
            }
        }
    }

    $("#"+containerId).append(table);
}


////////////////////////////////////////////////////////////////////////////////////////////////////
// PAGE IMAGE ELEMENTS
// 
App.prototype.setClusterImage = function (onFinishFn) {
    var img = $("#clusterImg");
    {
    //if (this.network.getDicedParent().length > 0) {
    //    var parent = img.parent();
    //    parent.addClass("text-center").addClass("p-5");
    //    parent.text("Image not available for this cluster");
    //    $("#downloadClusterImage").hide();
    //    this.progress.stop();
    //} else {
        var fileName = this.network.getImage();
        var that = this;
        if (Array.isArray(fileName))
            ;//TODO:
        else
            img
                .attr("src", this.dataDir + "/" + fileName + "_sm.png")
                .on("load", function () { that.addClusterHotspots(img); that.progress.stop(); onFinishFn(); })
                .on("error", function () { that.progress.stop(); });
        $("#downloadClusterImage").click(function (e) {
            e.preventDefault();
            window.location.href = that.getDownloadUrl("net");
        });
    }
}
// This should be called on the image
App.prototype.addClusterHotspots = function (img) {
    var parent = img.parent();
    var w = img.width() / 100;
    var h = img.height() / 100;
    var that = this;
    var imgmap = $('<map name="clusterHotspotMap" id="clusterHotspotMap"></map>');
    $.each(this.network.getRegions(), function (i, data) {
        var coords = [data.coords[0] * w, data.coords[1] * h, data.coords[2] * w, data.coords[3] * h];
        var coordStr = coords.join(",");
        var shape = $('<area shape="rect" coords="' + coordStr + '" id="cluster-region-' + data.id + '" href="' + getUrlFn(data.id, that.version) + '">');
        shape
            .click(function () {
                goToUrlFn(data.id, that.version);
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
    var width = $("#clusterImg").width() + "px";
    var padding = -1;
    $("#clusterNums").removeClass("w-100").css({width: width});
    $.each(this.network.getRegions(), function (i, data) {
        var theName = data.coords[1] == 0 ? data.name : "";
        var obj = $('<span id="cluster-num-text-' + data.id + '">' + theName + "</span>");
        parent.append(obj);
        // Calculate the width of the text to properly align text for small clusters
        var offset = (obj.width() / pw * 100 / 2) + padding;
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
                item = '<li class="breadcrumb-item"><a href="?id=' + parentId + '&v=' + this.version + '">';
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
    var table = $('<table class="table table-sm w-100"></table>');
    var head = $('<thead><tr><th>TIGR ID</th><th>TIGR Description</th></tr></thead>');
    var body = $('<tbody></tbody>');
    var text = "";
    for (var i = 0; i < tigr.length; i++) {
        body.append('<tr><td>' + tigr[i][0] + '</td><td>' + tigr[i][1] + '</td></tr>');
        text += tigr[i][0] + "\t" + tigr[i][1] + "\n";
    }
    table.append(head).append(body);
    if (tigr.length > 0) {
        $("#tigrList").append(table);
        $("#dataAvailableTigr").click(function () {
            $("#tigrListModal").modal();
        });
        $("#dataAvailableTigr").enableDataAvailableButton();
    }
}
App.prototype.checkForKegg = function () {
    if (!this.network.hasKeggIds())
        return;
    var that = this;
    //$("#keggList").append("<div>This cluster contains " + parseInt(this.network.getKeggCount()).toLocaleString() + " KEGG-annotated sequences.</div>");
    $("#dataAvailableKegg").click(function () {
        that.network.getKeggIds(that.version, function (id) {
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
    if (this.network.getRegions().length > 0) {
        var headHtml = '<thead><tr class="text-center"><th>Cluster</th>';
        if (this.network.Id != "fullnetwork") //TODO: HACK
            headHtml += '<th>SFLD Subgroup</th>';
        headHtml += '<th>UniProt</th><th>UniRef90</th><th>UniRef50</th></tr></thead>';
        var head = table.append(headHtml);
        var body = table.append('<tbody class="row-clickable text-center"></tbody>');
        $.each(this.network.getRegions(), function (i, data) {
            var row = $('<tr data-node-id="' + data.id + '"></tr>');
            var size = that.network.getSizes(data.id);
            var sfldIds = that.network.getSfldIds(data.id);
            var sfldDesc = that.network.getNetworkSfldTitle(data.id);
            var sfldDisplayFn = function (sfldId, desc) {
                return "<span style=\"color: " + that.network.getSfldColor(sfldId) + ";\">" + desc + "</span>";
            };
            if (!sfldDesc) {
                for (var i = 0; i < sfldIds.length; i++) {
                    var sfldId = sfldIds[i];
                    var sfldIdStr = sfldId ? " [" + sfldId + "]" : "";
                    if (sfldDesc.length)
                        sfldDesc += '; ';
                    sfldDesc += sfldDisplayFn(sfldId, that.network.getSfldDesc(sfldId));
                    //sfldDesc += sfldDisplayFn(sfldId, that.network.getSfldDesc(sfldId) + sfldIdStr);
                }
            } else {
                var sfldIdStr = sfldIds.join("; ");
                if (sfldIdStr)
                    sfldIdStr = " [" + sfldIdStr + "]";
                sfldDesc = sfldDisplayFn(sfldIds[0], sfldDesc);
                //sfldDesc = sfldDisplayFn(sfldIds[0], sfldDesc + sfldIdStr);
            }
            var rowHtml = "<td>" + data.name + "</td>";
            if (that.network.Id != "fullnetwork") //TODO: HACK
                rowHtml += "<td>" + sfldDesc + "</td>";
            rowHtml += "<td>" + commify(size.uniprot) + "</td><td>" + commify(size.uniref90) + "</td><td>" + commify(size.uniref50) + "</td>";
            row.append(rowHtml);
            body.append(row);
        });
    } else if (this.network.getDicedChildren().length > 0) {
        var head = table.append('<thead><tr><th>Sub-Cluster</th></tr></thead>');
        var body = table.append('<tbody class="row-clickable text-center"></tbody>');
        $.each(this.network.getDicedChildren(), function (i, data) {
            var row = $('<tr data-node-id="' + data.id + '"></tr>');
            row.append("<td>" + data.id + "</td>");
            body.append(row);
        });
    } else if (this.network.getSubgroups().length > 0) {
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


App.prototype.addAltSsns = function (div) {
    var as = this.alignmentScore;
    var that = this;
    var table = $('<table class="table table-sm text-center w-auto"></table>');
    table.append('<thead><tr><th>Alignment Score</th></thead>');
    var body = table.append('<tbody class="row-clickable text-center"></tbody>');
    table.append(body);
    if (as) {
        var row = $('<tr data-node-id="' + that.network.Id + '" data-alignment-score="' + '"></tr>');
        row.append("<td>" + "Default" + "</td>");
        body.append(row);
    }
    $.each(this.network.getAltSsns(), function (i, data) {
        if (data[0]) {
            var row = $('<tr data-node-id="' + that.network.Id + '" data-alignment-score="' + data[0] + '"></tr>');
            row.append("<td>" + data[0] + "</td>");
            body.append(row);
        }
    });
    div.append(table).show();
}


////////////////////////////////////////////////////////////////////////////////////////////////////
// SUNBURST
//
App.prototype.showSunburst = function() {
}

function triggerDownload (imgURI) {
  var evt = new MouseEvent('click', {
    view: window,
    bubbles: false,
    cancelable: true
  });

  var a = document.createElement('a');
  a.setAttribute('download', 'MY_COOL_IMAGE.png');
  a.setAttribute('href', imgURI);
  a.setAttribute('target', '_blank');

  a.dispatchEvent(evt);
}



App.prototype.addSunburstFeature = function() {
    var hasData = this.network.getDisplayFeatures().length > 0;
    if (!hasData)
        return;

    var that = this;
    var Colors = getSunburstColorFn();

    // Doesn't work
    //var setupPngDownload = function() {
    //    var svg = $("#sunburstChart svg")[0];
    //    var canvasObj = $("#sunburstPngCanvas");
    //    var canvas = canvasObj[0];
    //    $("#sunburstPng").click(function() {
    //        var ctx = canvas.getContext('2d');
    //        var data = (new XMLSerializer()).serializeToString(svg);
    //        var DOMURL = window.URL || window.webkitURL || window;
    //      
    //        var img = new Image();
    //        var svgBlob = new Blob([data], {type: 'image/svg+xml;charset=utf-8'});
    //        var url = DOMURL.createObjectURL(svgBlob);
    //      
    //        img.onload = function () {
    //            ctx.drawImage(img, 0, 0);
    //            DOMURL.revokeObjectURL(url);
    //      
    //            var imgURI = canvas
    //                .toDataURL('image/png')
    //                .replace('image/png', 'image/octet-stream');
    //      
    //            //triggerDownload(imgURI);
    //        };
    //        img.src = url;
    //    });
    //};

    var setupSvgDownload = function() {
        var svg = $("#sunburstChart svg")[0];
        $("#sunburstSvg").click(function() {
            var svgData = svg.outerHTML;
            var svgBlob = new Blob([svgData], {type:"image/svg+xml;charset=utf-8"});
            var svgUrl = URL.createObjectURL(svgBlob);
            var downloadLink = document.createElement("a");
            downloadLink.href = svgUrl;
            downloadLink.download = "newesttree.svg";
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        });
    };

    var addCurViewNumSeq = function() {
        var numUniProt = commify(that.sbCurrentData.numSequences);
        var idStr = that.sbCurrentData.numSequences > 1 ? "IDs" : "ID";
        $("#sunburstIdNums").text(numUniProt + " UniProt " + idStr + " visible");
    };


    $("#dataAvailableSunburst").click(function() {
        var progress = new Progress($("#sunburstProgressLoader"));
        progress.start();
        $("#sunburstModal").modal();
        $("#sunburstChart").empty();
        var parms = {cid: that.network.Id, a: "tax", v: that.version};
        if (that.alignmentScore)
            parms.as = that.alignmentScore;
        $.ajax({
            dataType: "json",
            url: "getdata.php",
            data: parms,
            success: function(treeData) {
                if (typeof(treeData.valid) !== "undefined" && treeData.valid === "false") {
                    //TODO: handle error
                    alert(treeData.message);
                } else {
                    that.sbRootData = treeData;
                    that.sbCurrentData = treeData;
                    addCurViewNumSeq();
                    var sb = Sunburst()
                        .width(600)
                        .height(600)
                        .data(treeData)
                        .label("node")
                        .size("numSpecies")
                        .color(Colors)
                        .excludeRoot(true)
                        //.color((d, parent) => color(parent ? parent.data.name : null))
                        //.tooltipContent((d, node) => `Size: <i>${node.value}</i>`)
                        (document.getElementById("sunburstChart"));
                    sb.onClick(function(data) {
                        that.sbCurrentData = data;
                        addCurViewNumSeq();
                        sb.focusOnNode(data);
                    });
                    //setupSvgDownload();
                    progress.stop();
                }
            }
        });
    }).enableDataAvailableButton();

    this.sbDownloadFile = null;
    var makeTextFile = function(text) {
        var data = new Blob([text], {type: 'text/plain'});
    
        // If we are replacing a previously generated file we need to
        // manually revoke the object URL to avoid memory leaks.
        if (that.sbDownloadFile !== null) {
          window.URL.revokeObjectURL(that.sbDownloadFile);
        }
    
        that.sbDownloadFile = window.URL.createObjectURL(data);
    
        return that.sbDownloadFile;
    };

    var fixNodeName = function(str) {
        return str.replace(/[^a-z0-9]/gi, "_");
    };

    var getIdType = function() {
        return $("input[name='sunburstIdType']:checked").val();
    };

    $("#sunburstDlIds").click(function() {
        var idType = getIdType();
        var ids = getIdsFromTree(that.sbCurrentData, idType);
        var fname = that.network.Id + "_";
        if (that.alignmentScore)
            fname += "AS" + that.alignmentScore + "_";
        if (idType != "uniref")
            fname += idType + "_";
        fname += fixNodeName(that.sbCurrentData.node) + ".txt";
        var text = ids.join("\r\n");
        $("#sbDownloadLink").attr("download", fname);
        $("#sbDownloadLink").attr("href", makeTextFile(text));
        $("#sbDownloadLink").click(function() {
            $("#sunburstDownloadModal").modal();
        });
        $("#sunburstDownloadModal").modal();
    });
    $("#sunburstDlFasta").click(function() {
        var idType = getIdType();
        var progress = new Progress($("#downloadProgressLoader"));
        progress.start();
        var ids = getIdsFromTree(that.sbCurrentData, idType);
        var form = $('<form method="POST" action="getfasta.php"></form>');
        var fc = $('<input name="c" type="hidden">').val(that.network.Id);
        form.append(fc);
        var fv = $('<input name="v" type="hidden">').val(that.version);
        form.append(fv);
        var fo = $('<input name="o" type="hidden">').val(fixNodeName(that.sbCurrentData.node));
        form.append(fo);
        var fids = $('<input name="ids" type="hidden">').val(JSON.stringify(ids));
        form.append(fids);
        if (that.alignmentScore) {
            var fas = $('<input name="as" type="hidden">').val(that.alignmentScore);
            form.append(fas);
        }
        var fidtype = $('<input name="it" type="hidden">').val(idType);
        form.append(fidtype);
        $("body").append(form);
        $("#sbDownloadBtn").hide();
        $("#sunburstDownloadModal h5").show();
        $("#sunburstDownloadModal").modal();
        form.submit();
        setTimeout(function() {
            $("#sunburstDownloadModal").modal("hide");
            progress.stop();
        }, 1000);


        //$.ajax({
        //    type: "POST",
        //    dataType: "json",
        //    url: "getfasta.php",
        //    data: parms,
        //    success: function(data) {
        //        $("#sunburstDownloadModal").modal("hide");
        //    }
        //});
        //$("#sbDownloadLink").attr("href", );
    });
}


function getIdsFromTree(data, idType) {
    var nextLevel = function(level) {
        var ids = [];
        // Bottom level
        if (typeof level.sequences !== "undefined") {
            for (var i = 0; i < level.sequences.length; i++) {
                var id = idType == "uniref50" ? level.sequences[i].sa50 : (idType == "uniref90" ? level.sequences[i].sa90 : level.sequences[i].seqAcc);
                //ids.push(level.sequences[i].seqAcc);
                ids.push(id);
            }
        } else {
            for (var i = 0; i < level.children.length; i++) {
                var nextIds = nextLevel(level.children[i]);
                for (var j = 0; j < nextIds.length; j++) {
                    ids.push(nextIds[j]);
                }
            }
        }
        return ids;
    };

    var ids = nextLevel(data);
    ids = Array.from(new Set(ids));
    return ids;
}



////////////////////////////////////////////////////////////////////////////////////////////////////
// UTILITY FUNCTIONS
//
function applyRowClickableFn(version) {
    $(".row-clickable tr")
        .mouseover(function () {
            $("#cluster-region-" + $(this).data("node-id")).mouseover();
        })
        .mouseout(function () {
            $("#cluster-region-" + $(this).data("node-id")).mouseout();
        })
        .click(function () {
            var id = $(this).data("node-id");
            var alignmentScore = $(this).data("alignment-score");
            if (!alignmentScore)
                alignmentScore = "";
            goToUrlFn(id, version, alignmentScore);
        });
}
function getPageClusterId() {
    var paramStr = window.location.search.substring(1);
    var params = paramStr.split("&");
    var reqId = "", version = DEFAULT_VERSION, as = "";
    for (var i = 0; i < params.length; i++) {
        var parts = params[i].split("=");
        if (parts[0] === "id")
            reqId = parts[1];
        else if (parts[0] === "v")
            version = parts[1];
        else if (parts[0] === "as")
            as = parts[1];
    }
    //TODO: validate version
    var data = {network_id: "", version: version}
    if (reqId)
        data.network_id = reqId;
    if (!isNaN(as))
        data.alignment_score = as;
    return data;
}
function getUrlFn(id, version, alignmentScore = "") {
    var url = "?id=" + id + "&v=" + version;
    if (alignmentScore)
        url += "&as=" + alignmentScore;
    return url;
}
function goToUrlFn(id, version, alignmentScore = "") {
    window.location = getUrlFn(id, version, alignmentScore);
}
App.prototype.getDownloadUrl = function(type, networkId = "") {
    if (!networkId)
        networkId = this.network.Id;
    var extPos = type.indexOf(".");
    if (extPos >= 0)
        type = type.substr(0, extPos);
    //this.dataDir + "/weblogo.png"
    var url = "download.php?c=" + networkId + "&v=" + this.version + "&t=" + type;
    if (this.alignmentScore)
        url += "&as=" + this.alignmentScore;
    return url;
}
function copyToClipboard(id) {
    $("#"+id).show().select();
    document.execCommand("copy");
    $("#"+id).hide();
}
const sleep = (milliseconds) => {
    return new Promise(resolve => setTimeout(resolve, milliseconds))
}

function commify(num) {
    return parseInt(num).toLocaleString();
}

