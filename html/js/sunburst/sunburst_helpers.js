

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
// The license and copyright below apply to the hsvToRgb function and colors.
//
//
//
// A javascript library for drawing &quot;sunburst&quot; species trees using 
// the HTML5 canvas.
//
// jt6 20100611 WTSI
//
// $Id$
//
// Copyright (c) 2010: Genome Research Ltd.
// 
// Authors: Rob Finn (rdf@sanger.ac.uk), John Tate (jt6@sanger.ac.uk)
// 
// This is free software; you can redistribute it and/or modify it under
// the terms of the GNU General Public License as published by the Free Software
// Foundation; either version 2 of the License, or (at your option) any later
// version.
//
// This program is distributed in the hope that it will be useful, but WITHOUT
// ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
// FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
// details.
//
// You should have received a copy of the GNU General Public License along with
// this program. If not, see <http://www.gnu.org/licenses/>.
//----------------------------------------------------------------------------


/**
* Converts an HSV colour into an RGB colour.
* 
* @private
* @param h hue
* @param s saturation
* @param v value
* @returns {Array} reference to array containing R, G and B values
*/
function hsvToRgb ( h, s, v ) {
    var r, g, b,
        i,
        f, p, q, t;

    if ( s === 0 ) {
      // Achromatic (grey)
      r = g = b = v;
      return [Math.round(r * 255), Math.round(g * 255), Math.round(b * 255)];
    }

    h *= 360;
    h /= 60; // sector 0 to 5
    i = Math.floor(h);
    f = h - i; // factorial part of h
    p = v * (1 - s);
    q = v * (1 - s * f);
    t = v * (1 - s * (1 - f));

    switch(i) {
      case 0:  r = v; g = t; b = p; break;
      case 1:  r = q; g = v; b = p; break;
      case 2:  r = p; g = v; b = t; break;
      case 3:  r = p; g = q; b = v; break;
      case 4:  r = t; g = p; b = v; break;
      default: r = v; g = p; b = q; // case 5:
    }

    return [ Math.round(r * 255), Math.round(g * 255), Math.round(b * 255) ];
}


////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// IGB/EFI license
//
function getSunburstColorFn() {
    
    // From the sunbursts on http://pfam.xfam.org/
    var Colors = {
        "Archaea":                { "minH": 0.017453292519943295,
                                    "maxH": 0.6981317007977318 },
        "Bacteria":               { "minH": 0.8726646259971648,
                                    "maxH": 2.6179938779914944 },
        "unclassified sequences": { "minH": 3.141592653589793,
                                    "maxH": 3.1764992386296798 },
        "unclassified":           { "minH": 3.193952531149623,
                                    "maxH": 3.2288591161895095 },
        "Eukaryota":              { "minH": 4.363323129985824,
                                    "maxH": 5.235987755982989 },
        "Viruses":                { "minH": 5.410520681182422,
                                    "maxH": 5.759586531581287 },
        "Viroids":                { "minH": 5.777039824101231,
                                    "maxH": 5.846852994181004 },
        "other sequences":        { "minH": 5.8643062867009474,
                                    "maxH": 6.19591884457987 }
    };
    
    // Returns a hue
    var getKingdom = function(d, g) {
        // Root
        if (!g)
            return "";
        // Kingdom
        if (!g.parent)
            return d.node;
        while (g.depth > 1)
           g = g.parent;
        return g.data.node;
    };
    
    var getColor = function(Hdata, depth) {
        //return "blue";
        var maxDepth = 7; //TODO: determine this??
        var Hmax = 2 * Math.PI;
        var H = (Hdata.maxH - Hdata.minH) * (1 - depth / maxDepth) + Hdata.minH;
        H = H / Hmax;
        var S = 1;
        var V = S;
        var rgb = hsvToRgb(H, S, V);
        return "rgb(" + rgb.join(",") + ")";
    };
    
    return function(d, g) { // data object, graphics object
        var K = getKingdom(d, g);
        // Root
        if (!K || K == "Root")
            return "gray";
        if (typeof Colors[K] !== "undefined")
            return getColor(Colors[K], g.depth);
        else
            return "gray";
        // var p = d.parent;
        // while (p && p != "root")
            // p
        //if (typeof parent === "undefined" || !parent)
        //    return "";
        //var p = parent;
        //while (p && p.
    };
}
