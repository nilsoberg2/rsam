

function parseNetworkJson(json) {
    try {
        var data = JSON.parse(json);
        return data;
    } catch (e) {
        console.log("Invalid data (" + json + ")");
        console.log(e);
        return false;
    }
}
function Network(networkId, networkData) {
    this.Id = networkId;
    this.data = networkData.cluster;
    if (typeof this.data === "undefined")
        this.data = {};
    this.network_map = networkData.network_map;
    this.sfld_map = networkData.sfld_map;
    this.sfld_desc = networkData.sfld_desc;
    this.enzymecodes = networkData.enzymecodes;
    if (typeof this.data.public === "undefined")
        this.data.public = {};
    if (typeof this.data.families === "undefined")
        this.data.families = {};
}
Network.prototype.getPageTitle = function() {
    return typeof this.data.title !== "undefined" ? this.data.title : "Title";
}
Network.prototype.getDescription = function() {
    return typeof this.data.desc !== "undefined" ? this.data.desc : "";
}
Network.prototype.getName = function() {
    return typeof this.data.name !== "undefined" ? this.data.name : "family";
}
Network.prototype.getImage = function() {
    return this.data.image;
}
Network.prototype.getSubgroups = function() {
    return Array.isArray(this.data.subgroups) ? this.data.subgroups : [];
}
Network.prototype.getRegions = function() {
    return Array.isArray(this.data.regions) ? this.data.regions : [];
}
Network.prototype.getTigr = function() {
    return Array.isArray(this.data.families.tigr) ? this.data.families.tigr : [];
}
// Since there are potentially many KEGG IDs, we get the list of IDs async.
Network.prototype.getKeggCount = function() {
    // The number of KEGG IDs is returned with the network JSON, but not the ID list.
    return typeof this.data.public.kegg_count !== "undefined" ? this.data.public.kegg_count : 0;
}
// ASYNC
Network.prototype.getKeggIds = function(addKeggIdFn, finishFn) {
    $.get("getdata.php", {a: "kegg", cid: this.Id}, function(dataStr) {
        var data = false;
        try {
            data = JSON.parse(dataStr);
        } catch (e) {
            console.log("Invalid kegg data (" + dataStr + ")");
            console.log(e);
            data = false;
        }
        if (data.valid) {
            for (var i = 0; i < data.kegg.length; i++) {
                addKeggIdFn(data.kegg[i]);
            }
        }
        finishFn();
    });
}
Network.prototype.getSizes = function (netId = "") {
    if (netId) {
        return this.network_map[netId].size;
    }
    if (typeof this.data.size !== "undefined" && this.data.size.uniprot > 0) {
        return this.data.size;
    } else {
        return false;
    }
}
Network.prototype.getSwissProtFunctions = function () {
    return Array.isArray(this.data.public.swissprot) ? this.data.public.swissprot : [];
}
Network.prototype.getPdb = function () {
    return Array.isArray(this.data.public.pdb) ? this.data.public.pdb: [];
}
Network.prototype.getEnzymeCodes = function () {
    return typeof this.enzymecodes !== "undefined" ? this.enzymecodes : {};
}
Network.prototype.getDisplayFeatures = function () {
    return Array.isArray(this.data.display) ? this.data.display : [];
}
Network.prototype.getDownloadFeatures = function () {
    return Array.isArray(this.data.download) ? this.data.download : [];
}
Network.prototype.getNetworkMapName = function (networkId) {
    return typeof this.network_map[networkId] !== "undefined" ? this.network_map[networkId].name : "";
}
Network.prototype.getSfldDesc = function (id) {
    return typeof this.sfld_desc[id] !== "undefined" ? this.sfld_desc[id].desc : "";
}
Network.prototype.getSfldColor = function (id) {
    return typeof this.sfld_desc[id] !== "undefined" ? this.sfld_desc[id].color : "";
}
Network.prototype.getSfldIds = function (cid) {
    return typeof this.sfld_map[cid] !== "undefined" ? this.sfld_map[cid] : [];
}


