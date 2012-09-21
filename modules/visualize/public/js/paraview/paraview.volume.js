var paraview;
var midas = midas || {};
midas.visualize = midas.visualize || {};

midas.visualize.renderers = {};
midas.visualize.DISTANCE_FACTOR = 1.6; // factor to zoom the camera out by

midas.visualize.start = function () {
    // Create a paraview proxy
    var file = json.visualize.url;
    var container = $('#renderercontainer');

    if(typeof Paraview != 'function') {
        alert('Paraview javascript was not fetched correctly from server.');
        return;
    }

    paraview = new Paraview("/PWService");
    paraview.errorListener = {
        manageError: function(error) {
            midas.createNotice('A ParaViewWeb error occurred; check the console for information', 4000, 'error');
            console.log(error);
            return true;
        }
    };
    paraview.createSession("midas", "volume render", "default");

    midas.visualize.input = paraview.OpenDataFile({filename: file});
    paraview.Show();

    var imageData = paraview.GetDataInformation();
    midas.visualize.bounds = imageData.Bounds;
    midas.visualize.maxDim = Math.max(midas.visualize.bounds[1] - midas.visualize.bounds[0],
                                      midas.visualize.bounds[3] - midas.visualize.bounds[2],
                                      midas.visualize.bounds[5] - midas.visualize.bounds[4]);
    midas.visualize.minVal = imageData.PointData.Arrays[0].Ranges[0][0];
    midas.visualize.maxVal = imageData.PointData.Arrays[0].Ranges[0][1];
    midas.visualize.imageWindow = [midas.visualize.minVal, midas.visualize.maxVal];

    midas.visualize.midI = (midas.visualize.bounds[0] + midas.visualize.bounds[1]) / 2.0;
    midas.visualize.midJ = (midas.visualize.bounds[2] + midas.visualize.bounds[3]) / 2.0;
    midas.visualize.midK = Math.floor((midas.visualize.bounds[4] + midas.visualize.bounds[5]) / 2.0);

    if(midas.visualize.bounds.length != 6) {
        console.log('Invalid image bounds:');
        console.log(midas.visualize.bounds);
        return;
    }

    midas.visualize.activeView = paraview.CreateIfNeededRenderView();
    midas.visualize.activeView.setCameraFocalPoint([midas.visualize.midI,
                                                    midas.visualize.midJ,
                                                    midas.visualize.midK]);
    midas.visualize.activeView.setCameraPosition([
      midas.visualize.midI - midas.visualize.DISTANCE_FACTOR*midas.visualize.maxDim,
      midas.visualize.midJ,
      midas.visualize.midK]);
    midas.visualize.activeView.setCameraViewUp([0.0, 0.0, 1.0]);
    midas.visualize.activeView.setCameraParallelProjection(false);
    midas.visualize.activeView.setCenterOfRotation(midas.visualize.activeView.getCameraFocalPoint());
    midas.visualize.activeView.setBackground([0.0, 0.0, 0.0]);
    midas.visualize.activeView.setBackground2([0.0, 0.0, 0.0]); //solid black background

    midas.visualize.defaultColorMap = [
       midas.visualize.minVal, 0.0, 0.0, 0.0,
       midas.visualize.maxVal, 1.0, 1.0, 1.0];
    midas.visualize.colorMap = midas.visualize.defaultColorMap;

    midas.visualize.lookupTable = paraview.GetLookupTableForArray('MetaImage', 1);
    midas.visualize.lookupTable.setRGBPoints(midas.visualize.colorMap);
    midas.visualize.lookupTable.setScalarRangeInitialized(1.0);
    midas.visualize.lookupTable.setColorSpace(0); // 0 corresponds to RGB

    // Create the scalar opacity transfer function
    midas.visualize.sof = paraview.CreatePiecewiseFunction({
        Points: [midas.visualize.minVal, 0.0, 0.5, 0.0,
                 midas.visualize.maxVal, 1.0, 0.5, 0.0]
    });

    paraview.SetDisplayProperties({
        view: midas.visualize.activeView,
        ScalarOpacityFunction: midas.visualize.sof,
        Representation: 'Volume',
        ColorArrayName: 'MetaImage',
        LookupTable: midas.visualize.lookupTable
    });

    midas.visualize.switchRenderer(true); // render in the div
    $('img.visuLoading').hide();
    container.show();
};

/**
 * Initialize or re-initialize the renderer within the DOM
 */
midas.visualize.switchRenderer = function (first) {
    if(midas.visualize.renderers.js == undefined) {
        midas.visualize.renderers.js = new JavaScriptRenderer("jsRenderer", "/PWService");
        midas.visualize.renderers.js.init(paraview.sessionId, midas.visualize.activeView.__selfid__);
        $('img.toolButton').show();
    }

    if(!first) {
        midas.visualize.renderers.current.unbindToElementId('renderercontainer');
    }
    midas.visualize.renderers.current = midas.visualize.renderers.js;
    midas.visualize.renderers.current.bindToElementId('renderercontainer');
    var el = $('#renderercontainer');
    midas.visualize.renderers.current.setSize(el.width(), el.height());
    midas.visualize.renderers.current.start();
};

/**
 * Display the subset of the volume defined by the bounds list
 * of the form [xMin, xMax, yMin, yMax, zMin, zMax]
 */
midas.visualize.renderSubgrid = function (bounds) {
    if(midas.visualize.subgrid) {
      paraview.Hide({proxy: midas.visualize.subgrid});
    }
    paraview.SetActiveSource([midas.visualize.input]);
    midas.visualize.subgrid = paraview.ExtractSubset({
        VOI: bounds
    });
    paraview.SetDisplayProperties({
        proxy: midas.visualize.subgrid,
        view: midas.visualize.activeView,
        ScalarOpacityFunction: midas.visualize.sof,
        Representation: 'Volume',
        ColorArrayName: 'MetaImage',
        LookupTable: midas.visualize.lookupTable
    });
    paraview.Hide({proxy: midas.visualize.input});
};

/**
 * Display information about the volume
 */
midas.visualize.populateInfo = function () {
    $('#boundsXInfo').html(midas.visualize.bounds[0]+' .. '+midas.visualize.bounds[1]);
    $('#boundsYInfo').html(midas.visualize.bounds[2]+' .. '+midas.visualize.bounds[3]);
    $('#boundsZInfo').html(midas.visualize.bounds[4]+' .. '+midas.visualize.bounds[5]);
    $('#scalarRangeInfo').html(midas.visualize.minVal+' .. '+midas.visualize.maxVal);
};

/**
 * Get the plot data from the scalar opacity function
 */
midas.visualize.getSofCurve = function () {
    var points = midas.visualize.sof.getPoints();
    var curve = [];
    for(var i = 0; i < points.length; i++) {
        curve[i] = [points[4*i], points[4*i+1]];
    }
    return curve;
};

/**
 * Setup the color mapping controls
 */
midas.visualize.setupColorMapping = function () {
    var dialog = $('#scmDialogTemplate').clone();
    dialog.removeAttr('id');
    $('#scmEditAction').click(function () {
        midas.showDialogWithContent('Scalar color mapping',
          dialog.html(), false, {modal: false, width: 360});
        var container = $('div.MainDialog');
        var pointListDiv = container.find('div.rgbPointList');

        function renderPointList (colorMap) {
            for(var i = 0; i < colorMap.length; i += 4) {
                var rgbPoint = $('#scmPointMapTemplate').clone();
                var r = Math.round(255*colorMap[i+1]);
                var g = Math.round(255*colorMap[i+2]);
                var b = Math.round(255*colorMap[i+3])
                rgbPoint.removeAttr('id').appendTo(pointListDiv).show();
                rgbPoint.find('input.scmScalarValue').val(colorMap[i]);
                if(i < 8) { // first two values must be present (min and max)
                    rgbPoint.find('input.scmScalarValue').attr('disabled', 'disabled');
                }
                else {
                    rgbPoint.find('button.scmDeletePoint').show().click(function () {
                        $(this).parents('div.rgbPointContainer').remove();
                    });
                }
                rgbPoint.find('.scmColorPicker').ColorPicker({
                    color: {
                        r: r,
                        g: g,
                        b: b
                    },
                    onSubmit: function(hsb, hex, rgb, el) {
                        $(el).css('background-color', 'rgb('+rgb.r+','+rgb.g+','+rgb.b+')');
                    }
                }).css('background-color', 'rgb('+r+','+g+','+b+')');
            }
        };
        renderPointList(midas.visualize.colorMap);

        container.find('button.scmAddPoint').unbind('click').click(function () {
            var rgbPoint = $('#scmPointMapTemplate').clone();
            rgbPoint.removeAttr('id').appendTo(pointListDiv).show();
            rgbPoint.find('input.scmScalarValue').val(midas.visualize.defaultColorMap[0]);
            rgbPoint.removeAttr('id').appendTo(pointListDiv).show();
            rgbPoint.find('button.scmDeletePoint').show().click(function () {
                rgbPoint.remove();
            });
            rgbPoint.find('.scmColorPicker').ColorPicker({
                color: {
                    r: 0,
                    g: 0,
                    b: 0
                },
                onSubmit: function(hsb, hex, rgb, el) {
                    $(el).css('background-color', 'rgb('+rgb.r+','+rgb.g+','+rgb.b+')');
                }
            }).css('background-color', 'rgb(0, 0, 0)');
        });

        container.find('button.scmClose').unbind('click').click(function () {
            container.dialog('close');
        });
        container.find('button.scmReset').unbind('click').click(function () {
            pointListDiv.html('');
            renderPointList(midas.visualize.defaultColorMap);
        });
        container.find('button.scmApply').unbind('click').click(function () {
            var colorMap = [];
            $.each(pointListDiv.find('div.rgbPointContainer'), function(idx, template) {
                var scalar = parseFloat($(template).find('input.scmScalarValue').val());
                var tokens = $(template).find('div.scmColorPicker')
                  .css('background-color').match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
                colorMap.push(scalar, parseFloat(tokens[1]) / 255.0, parseFloat(tokens[2]) / 255.0,
                  parseFloat(tokens[3]) / 255.0);
            });
            midas.visualize.colorMap = colorMap;
            midas.visualize.lookupTable.setRGBPoints(colorMap);
            paraview.SetDisplayProperties({
                LookupTable: midas.visualize.lookupTable
            });
        });
    });
};

/**
 * Setup the scalar opacity function controls
 */
midas.visualize.setupScalarOpacity = function () {
    var dialog = $('#sofDialogTemplate').clone();
    dialog.removeAttr('id');
    $('#sofEditAction').click(function () {
        midas.showDialogWithContent('Scalar opacity function',
          dialog.html(), false, {modal: false, width: 500});
        var container = $('div.MainDialog');
        container.find('div.sofPlot').attr('id', 'sofChartDiv');

        midas.visualize.sofPlot = $.jqplot('sofChartDiv', [midas.visualize.getSofCurve()], {
            series:[{showMarker:true}],
            axes: {
                xaxis: {
                    min: midas.visualize.minVal,
                    max: midas.visualize.maxVal,
                    label: 'Scalar value',
                    labelRenderer: $.jqplot.CanvasAxisLabelRenderer,
                    labelOptions: {
                        fontSize: '8pt'
                    }
                },
                yaxis: {
                    min: 0,
                    max: 1,
                    label: 'Opacity',
                    labelRenderer: $.jqplot.CanvasAxisLabelRenderer,
                    labelOptions: {
                        angle: 270,
                        fontSize: '8pt'
                    },
                    tickInterval: 1.0
                }
            },
            grid: {
                drawGridlines: false
            },
            cursor: {
                show: true,
                style: 'pointer',
                tooltipLocation:'se'
            },
            highlighter: {
                show: true,
                sizeAdjust: 7.5
            }
        });
        container.find('button.sofClose').click(function () {
            $('div.MainDialog').dialog('close');
        });
        container.find('button.sofApply').click(function () {
            midas.visualize.applySofCurve();
        });
        container.find('button.sofReset').click(function () {
            midas.visualize.sofPlot.series[0].data = [
              [midas.visualize.minVal, 0],
              [midas.visualize.maxVal, 1]];
            midas.visualize.sofPlot.replot();
            midas.visualize.setupSofPlotBindings();
            container.find('div.sofPointEdit').hide();
        });
        midas.visualize.setupSofPlotBindings();
    });
};

/**
 * Called when the "apply" button on the sof dialog is clicked;
 * updates the sof in paraview based on the jqplot curve
 */
midas.visualize.applySofCurve = function () {
    // Create the scalar opacity transfer function
    var points = [];
    var curve = midas.visualize.sofPlot.series[0].data;
    for(var idx in curve) {
        points.push(curve[idx][0], curve[idx][1], 0.5, 0.0);
    }

    midas.visualize.sof = paraview.CreatePiecewiseFunction({
        Points: points
    });

    paraview.SetDisplayProperties({
        ScalarOpacityFunction: midas.visualize.sof
    });
};

/**
 * Must call this anytime a redraw or replot is called on the sof plot
 */
midas.visualize.setupSofPlotBindings = function () {

    // Clicking an existing point should let you change its values
    $('#sofChartDiv').bind('jqplotDataClick', function (ev, seriesIndex, pointIndex, data) {
        var container = $('div.MainDialog').find('div.sofPointEdit');
        container.find('input.scalarValueEdit').val(data[0]);
        container.find('input.opacityValueEdit').val(data[1]);
        container.show();

        container.find('button.pointUpdate').unbind('click').click(function () {
            var s = parseFloat(container.find('input.scalarValueEdit').val());
            var o = parseFloat(container.find('input.opacityValueEdit').val());
            midas.visualize.sofPlot.series[0].data[pointIndex] = [s, o];
            midas.visualize.sofPlot.replot();
            midas.visualize.setupSofPlotBindings();
        });
        container.find('button.pointDelete').unbind('click').click(function () {
            midas.visualize.sofPlot.series[0].data.splice(pointIndex, 1);
            midas.visualize.sofPlot.replot();
            midas.visualize.setupSofPlotBindings();
        });
    });

    // Clicking on the plot (except on an existing point) should add a new one
    $('#sofChartDiv').bind('jqplotClick', function (ev, seriesIndex, pointIndex, data) {
        if(data) {
            return; // we use the data click handler for this
        }
        $('div.MainDialog').find('div.sofPointEdit').hide();
        // insert new data point in between closest x-axis values
        var inserted = false;
        var newData = [midas.visualize.sofPlot.series[0].data[0]];

        for(var i = 1; i < midas.visualize.sofPlot.series[0].data.length; i++) {
            if(!inserted && pointIndex.xaxis < midas.visualize.sofPlot.series[0].data[i][0]) {
                inserted = true;
                newData.push([pointIndex.xaxis, pointIndex.yaxis]);
            }
            newData.push(midas.visualize.sofPlot.series[0].data[i]);
        }
        if(!inserted) {
            newData.push([pointIndex.xaxis, pointIndex.yaxis]);
        }
        midas.visualize.sofPlot.series[0].data = newData;
        midas.visualize.sofPlot.replot();
        midas.visualize.setupSofPlotBindings();
    });
};

/**
 * Setup the extract subgrid controls
 */
midas.visualize.setupExtractSubgrid = function () {
    var dialog = $('#extractSubgridDialogTemplate').clone();
    dialog.removeAttr('id');
    $('#extractSubgridAction').click(function () {
        midas.showDialogWithContent('Select subgrid bounds',
          dialog.html(), false, {modal: false, width: 560});
        var container = $('div.MainDialog');
        container.find('.sliderX').slider({
            range: true,
            min: midas.visualize.bounds[0],
            max: midas.visualize.bounds[1],
            values: [midas.visualize.bounds[0], midas.visualize.bounds[1]],
            slide: function (event, ui) {
                container.find('.extractSubgridMinX').val(ui.values[0]);
                container.find('.extractSubgridMaxX').val(ui.values[1]);
            }
        });
        container.find('.sliderY').slider({
            range: true,
            min: midas.visualize.bounds[2],
            max: midas.visualize.bounds[3],
            values: [midas.visualize.bounds[2], midas.visualize.bounds[3]],
            slide: function (event, ui) {
                container.find('.extractSubgridMinY').val(ui.values[0]);
                container.find('.extractSubgridMaxY').val(ui.values[1]);
            }
        });
        container.find('.sliderZ').slider({
            range: true,
            min: midas.visualize.bounds[4],
            max: midas.visualize.bounds[5],
            values: [midas.visualize.bounds[4], midas.visualize.bounds[5]],
            slide: function (event, ui) {
                container.find('.extractSubgridMinZ').val(ui.values[0]);
                container.find('.extractSubgridMaxZ').val(ui.values[1]);
            }
        });

        // setup spinboxes with feedback into the range sliders
        container.find('.extractSubgridMinX').spinbox({
            min: midas.visualize.bounds[0],
            max: midas.visualize.bounds[1]
        }).change(function () {
            container.find('.sliderX').slider('option', 'values',
              [$(this).val(), container.find('.extractSubgridMaxX').val()]);
        }).val(midas.visualize.bounds[0]);
        container.find('.extractSubgridMaxX').spinbox({
            min: midas.visualize.bounds[0],
            max: midas.visualize.bounds[1]
        }).change(function () {
            container.find('.sliderX').slider('option', 'values',
              [container.find('.extractSubgridMinX').val(), $(this).val()]);
        }).val(midas.visualize.bounds[1]);
        container.find('.extractSubgridMinY').spinbox({
            min: midas.visualize.bounds[2],
            max: midas.visualize.bounds[3]
        }).change(function () {
            container.find('.sliderY').slider('option', 'values',
              [$(this).val(), container.find('.extractSubgridMaxY').val()]);
        }).val(midas.visualize.bounds[2]);
        container.find('.extractSubgridMaxY').spinbox({
            min: midas.visualize.bounds[2],
            max: midas.visualize.bounds[3]
        }).change(function () {
            container.find('.sliderY').slider('option', 'values',
              [container.find('.extractSubgridMinY').val(), $(this).val()]);
        }).val(midas.visualize.bounds[3]);
        container.find('.extractSubgridMinZ').spinbox({
            min: midas.visualize.bounds[4],
            max: midas.visualize.bounds[5]
        }).change(function () {
            container.find('.sliderZ').slider('option', 'values',
              [$(this).val(), container.find('.extractSubgridMaxZ').val()]);
        }).val(midas.visualize.bounds[4]);
        container.find('.extractSubgridMaxZ').spinbox({
            min: midas.visualize.bounds[4],
            max: midas.visualize.bounds[5]
        }).change(function () {
            container.find('.sliderZ').slider('option', 'values',
              [container.find('.extractSubgridMinZ').val(), $(this).val()]);
        }).val(midas.visualize.bounds[5]);

        // setup button actions
        container.find('button.extractSubgridApply').click(function () {
            midas.visualize.renderSubgrid([
              parseInt(container.find('.extractSubgridMinX').val()),
              parseInt(container.find('.extractSubgridMaxX').val()),
              parseInt(container.find('.extractSubgridMinY').val()),
              parseInt(container.find('.extractSubgridMaxY').val()),
              parseInt(container.find('.extractSubgridMinZ').val()),
              parseInt(container.find('.extractSubgridMaxZ').val())
            ]);
        });
        container.find('button.extractSubgridClose').click(function () {
            $('div.MainDialog').dialog('close');
        });
    });
};

midas.visualize.setupOverlay = function () {
    $('button.plusX').click(function () {
        midas.visualize.activeView.setCameraPosition([
          midas.visualize.midI - midas.visualize.DISTANCE_FACTOR*midas.visualize.maxDim,
          midas.visualize.midJ,
          midas.visualize.midK]);
        midas.visualize.activeView.setCameraViewUp([0.0, 0.0, 1.0]);
    });
    $('button.minusX').click(function () {
        midas.visualize.activeView.setCameraPosition([
          midas.visualize.midI + midas.visualize.DISTANCE_FACTOR*midas.visualize.maxDim,
          midas.visualize.midJ,
          midas.visualize.midK]);
        midas.visualize.activeView.setCameraViewUp([0.0, 0.0, 1.0]);
    });
    $('button.plusY').click(function () {
        midas.visualize.activeView.setCameraPosition([
          midas.visualize.midI,
          midas.visualize.midJ - midas.visualize.DISTANCE_FACTOR*midas.visualize.maxDim,
          midas.visualize.midK]);
        midas.visualize.activeView.setCameraViewUp([0.0, 0.0, 1.0]);
    });
    $('button.minusY').click(function () {
        midas.visualize.activeView.setCameraPosition([
          midas.visualize.midI,
          midas.visualize.midJ + midas.visualize.DISTANCE_FACTOR*midas.visualize.maxDim,
          midas.visualize.midK]);
        midas.visualize.activeView.setCameraViewUp([0.0, 0.0, 1.0]);
    });
    $('button.plusZ').click(function () {
        midas.visualize.activeView.setCameraPosition([
          midas.visualize.midI,
          midas.visualize.midJ,
          midas.visualize.midK - midas.visualize.DISTANCE_FACTOR*midas.visualize.maxDim]);
        midas.visualize.activeView.setCameraViewUp([0.0, 1.0, 0.0]);
    });
    $('button.minusZ').click(function () {
        midas.visualize.activeView.setCameraPosition([
          midas.visualize.midI,
          midas.visualize.midJ,
          midas.visualize.midK + midas.visualize.DISTANCE_FACTOR*midas.visualize.maxDim]);
        midas.visualize.activeView.setCameraViewUp([0.0, 1.0, 0.0]);
    });
};

$(window).load(function () {
    if(typeof midas.visualize.preInitCallback == 'function') {
        midas.visualize.preInitCallback();
    }

    json = jQuery.parseJSON($('div.jsonContent').html());
    midas.visualize.start(); // do the initial rendering
    midas.visualize.populateInfo();
    midas.visualize.setupExtractSubgrid();
    midas.visualize.setupScalarOpacity();
    midas.visualize.setupColorMapping();
    midas.visualize.setupOverlay();

    if(typeof midas.visualize.postInitCallback == 'function') {
        midas.visualize.postInitCallback();
    }
});

$(window).unload(function () {
    paraview.disconnect();
});

