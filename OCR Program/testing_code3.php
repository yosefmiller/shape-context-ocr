<html><head><title>Test page</title><link rel="shortcut icon" href="../images/virale/favicon-1.png"></head>
<body style="color: #000;">
	<img id="image" style="display:none" src=""/>
	<div id="drop-overlay" style="display:none;position:fixed;z-index:-10;width:98%;height:96%;opacity:0.6;background-color:#DFFAE8;border:dashed 5px #334AE7;">
		<span style="position:fixed;padding-top:15%;padding-left:40%;font-size:50px;color:#0010B3;">DROP HERE</span>
	</div>
	<button onclick="run_ocr_on_image();/* determineBestWeights(); ocr_master(150,50,0,500,100,1); */">Run OCR with default settings</button>
	<label style="color:black;margin-left:15px;"><input id="gridline_checkbox" type="checkbox" />View gridline structure on next run</label>
	<progress id="progress" max="8" style="margin-left: 30px; display:none;" value="0"></progress>
	<select id="select_image" style="margin-left: 15px;" onchange="changeImage();">
		<option>test_image_qa_app.png</option>
		<option selected>ocr_image1.png</option>
		<option>ocr_image2.png</option>
		<option>ocr_image3.png</option>
		<option>ocr_image5.jpg</option>
		<option>ocr_image6.png</option>
		<option>font_arial1.jpg</option>
		<option>font_arial2.jpg</option>
		<option>font_serif2.jpg</option>
		<option>font_cambria2.jpg</option>
		<option>font_cambria3.jpg</option>
	</select><br/><br/>
	<span id="exec_time_span" style="margin-left: 0px; color: black;"></span><br/>
	<span id="total_pixels_span" style="margin-left: 0px; color: black;"></span><br/>
	<span id="output_text_span" style="margin-left: 0px; font-size:20px; color: black;"></span>
	<br/><br/>
	<canvas id="canvas1" style="width:75%;min-width:750px; display:none;"></canvas><br/><br/>
	<span>Original Image:</span><br/>
	<canvas id="canvas" style="margin-left:0%; width:28%; display:none;"></canvas>
	<script type="text/javascript" src="hungarian.js"></script>
	<script>
		/* Initialize on page load: */
		var canvas = document.getElementById("canvas"),
			context = canvas.getContext("2d"),
			canvas1 = document.getElementById("canvas1"),
			context1 = canvas1.getContext("2d"),
			image = document.getElementById("image"),
			gridline_checkbox = document.getElementById("gridline_checkbox"),
			select_image = document.getElementById("select_image"),
			exec_time_span = document.getElementById("exec_time_span"),
			total_pixels_span = document.getElementById("total_pixels_span"),
			output_text_span = document.getElementById("output_text_span"),
			
			numEdgeHistPoints = 50,
			imageWidth, imageHeight,
			pixelImageData = [],
			pixel_D = [],
			createPixelObject,
			templatePixelValue,
			templateRowPercentage,
			debugMode = false,
			select_image_name,
			adjustedRowIndex,
			adjustedColumnIndex,
			outputText = [],				// this contains the final recognized text outputted by this OCR program.
			templateMatchesSummation = [],	// this contains summations for each template matched against. These are used to find the closest character match.
			
			letterTemplates = [],		// contains the default bitmap of all the letters.
			
			top_border = 0,
			bottom_border = 0,
			left_border = 0,
			right_border = 0,
			boxTop,boxBottom,boxLeft,boxRight,boxRows,boxColumns,
			data_row = 0,
			data_column = 0;
				
		function init_canvas1_variables () {
			window.canvas1_data = [],			// contains black/white data
			window.canvas1_rgb = [],			// contains rgb data for entire image
			window.canvas1_graystyle = [],			// contains graystyle data for entire image
			window.canvas1_blurredGraystyle = [],	// contains graystyle data for entire image
			window.canvas1_sobelEdgeGradient = [],	// contains extracted edge data from sobel method
			window.canvas1_sobelEdgeDirMap = [],	// contains direction data from sobel method
			window.canvas1_sobelEdgeGradMap = [],	// contains gradient data from sobel method
			window.canvas1_nonMaxSuppress = [],		// contains thinned out edges from non-max-suppression method which thins data from sobel method.
			window.canvas1_hysteresis = [],			// contains edges extracted from Canny Edge Detection with hysteresis.
			window.canvas1_characterEdges = [],		// contains edges extracted from Canny Edge Detection grouped together with connected edges.
			window.canvas1_characterBorders = [],	// contains the borders from each character detected from Canny Edge Detection.
			window.canvas1_limitedNumberEdges = [],	// contains a certain number of edges from each character extracted from Canny Edge Detection.
			window.canvas1_characterHistograms = [],// contains a histogram for each edge of each character.
			
			window.canvas1_cost = [],				// contains color cost for entire image
			window.canvas1_gradient = [],			// contains gradient for entire image
			window.canvas1_structure = [],			// contains the structure of all characters in image
			window.canvas1_not_stuctured = [[2]],	// stores location of structure within canvas1_structure that is pending future processing.
			window.canvas1_temp_not_stuctured = [],	// values are added to this while structuring each row/column and later stored in canvas1_not_stuctured.
			window.canvas1_character_location = [],	// contains the final locations of each character cut out.
			window.canvas1_template_summations = [],		// contains the summations of each template tested against each character.
			window.canvas1_character_black_percent = [],	// contains, for each character, the percent of each [row, column] that is black.
			window.canvas1_zoning_properties = [];			// contains, for each character, the mean and STD of 6 zones.
		}
		init_canvas1_variables();
		
		function init_canvas () {
			canvas.style.display = "block";
			canvas1.style.display = "block";
			
			imageWidth = image.width;
			imageHeight = image.height;
			
			canvas.width = imageWidth;
			canvas.height = imageHeight;
			context.drawImage(image, 0, 0,  imageWidth,  imageHeight);
			
			canvas1.width = imageWidth;
			canvas1.height = imageHeight;
			
			context1.mozImageSmoothingEnabled = false;
			context1.msImageSmoothingEnabled = false;
			context1.imageSmoothingEnabled = false;
			context1.drawImage(image, 0, 0,  imageWidth,  imageHeight);
			
			total_pixels_span.innerHTML = "Number of pixels in image: "+(imageWidth * imageHeight);
			
			/* Run the OCR. */
			//console.log("run this: ocr_master(160,50,0,500,100,1)");
			//console.log("parameters for the ocr_master function: colorGain (max rgb value of ink), costGain (max cost for rgb), gradientGain (out of use currently), compareGain (min gradient color difference to differenciate between text and background), largePixelGain, singlePixelGain");
			// console.log("ocr_master(160,50,0,500,100,1); parameters for the ocr_master function: colorGain (max rgb value of ink), costGain (max cost for rgb), gradientGain (out of use currently), compareGain (min gradient color difference to differenciate between text and background), largePixelGain, singlePixelGain");
			//ocr_master(160,50,0,500,100,1);
		};
		
		function changeImage () {
			if (window.location.protocol !== "file:") {
				select_image_name = select_image.value;
				image.src = "TestImages/" + select_image_name;
				image.onload = function () { init_canvas(); }
			}
		}
		changeImage();
		
		function loadLetterTemplates () {
			letterTemplates = window.letterTemplatesFromFile;
			console.log("Templates loaded.");
		}
		
		function update_progress (progress_value) {
			document.getElementById("progress").value = progress_value;
		}
		
		// this is the master function for the ocr
		function ocr_master (colorGain, costGain, gradientGain, compareGain, largePixelGain, singlePixelGain) {
			//init_canvas1_variables();
			//canvas1_data = [],	canvas1_graystyle = [],	canvas1_rgb = [],	canvas1_cost = [],	canvas1_gradient = [],	canvas1_structure = [],	outputText = [],
			//canvas1_not_stuctured = [[2]],	canvas1_temp_not_stuctured = [],	canvas1_character_location = [],	canvas1_template_summations = [],
			//canvas1_character_black_percent = [], canvas1_zoning_properties = [];
			output_text_span.innerHTML = ""; exec_time_span.innerHTML = "";
			
			console.clear();
			if (typeof(letterTemplates) !== "object") {console.error("Templates failed to load. Please try again."); return;}
			var startOCRtime = performance.now();	// begin timing the execution time.
			debugMode = gridline_checkbox.checked;	// check setting if to show grid-lines (debugging mode).
																										update_progress("0");
			//binarizeWithOtsu();						//console.log("Finished black-white conversion.");	update_progress("1");
			//convertToBW(colorGain, costGain);		
			//gradientFilter(gradientGain);			//console.log("Finished gradient filter.");			update_progress("2");
			//colorCompareFilter(compareGain);		//console.log("Finished color compare filter.");		update_progress("3");
			//removeBorder();							//console.log("Finished removing border.");			update_progress("4");
			//fixLargePixelNoise(largePixelGain);		//console.log("Finished large pixel noise fix.");		update_progress("5");
			////fixSinglePixelNoise(singlePixelGain);	//console.log("Finished single pixel noise fix.");	update_progress("6");
			//separateCharacters();					//console.log("Finished separating characters.");		update_progress("7");
			//orderCharactersByStructure();			//console.log("Finished sorting location array.");	update_progress("8");
			//display_canvas1();						console.log("Finished all pre-processing steps.");	update_progress("9");
			////generateTemplates();					console.log("Finished generating templates.");		update_progress("10");
			
			var startRecognitionTime = performance.now();
			templateMatching();						console.log("Finished template matching.");			update_progress("11");
			//findBestTemplateMatch(3, 1.2, 1, 50);	console.log("Finished finding best match.");		update_progress("12");
			
			/* Output the text. */
			output_text_span.innerHTML = "The recognized text is: ";
			for (var kk = 0; kk < outputText.length; kk++) { output_text_span.innerHTML += outputText[kk]; }
			
			/* Determine the total execution time. */
			var endOCRtime = performance.now();
			var elapsedPreProcessingTime = startRecognitionTime - startOCRtime;
			var elapsedRecognitionTime = endOCRtime - startRecognitionTime;
			var elapsedOCRtime = endOCRtime - startOCRtime;
			
			/* Output the total execution time. */
			exec_time_span.innerHTML = 'Execution time: ' + Math.round(elapsedOCRtime)/1000 + "s";
			exec_time_span.innerHTML += ';    Pre-Processing time: ' + Math.round(elapsedPreProcessingTime)/1000 + "s";
			exec_time_span.innerHTML += ';    Recognition time: ' + Math.round(elapsedRecognitionTime)/1000 + "s";
			
			// reset the large variables to lower the page's memory usage etc.
			//canvas1_data = [];
			canvas1_rgb = [];
			canvas1_cost = [];
			canvas1_gradient = [];
			
			//return "parameters for the ocr_master function: colorGain (max rgb value of ink), costGain (max cost for rgb), gradientGain (out of use currently), compareGain (min gradient color difference to differenciate between text and background), largePixelGain (remove blotches), singlePixelGain (number of pixels nearby [weighted based on location] that are black/white to detect if the pixel is a mistake)";
			return;
		}
		
		/* ~~~~~~~~~~~ DISPLAY FUNCTIONS: ~~~~~~~~~~~ **/
		var display_canvas1 = function () {
			createPixelObject = context.createImageData(imageWidth,imageHeight);
			var count_pixel_rgba = 0;
			for (data_row = 0; data_row < imageHeight; data_row++){	// for each row of pixels
				for (data_column = 0; data_column < imageWidth; data_column++){	// for each pixel in that row
					if (canvas1_data[data_row][data_column] == 2) {	// if it is gray (for testing)
						createPixelObject.data[count_pixel_rgba] = 200;		// r
						createPixelObject.data[count_pixel_rgba+1] = 200;		// g
						createPixelObject.data[count_pixel_rgba+2] = 200;		// b
						createPixelObject.data[count_pixel_rgba+3] = 255;	// a
						count_pixel_rgba += 4;
					}
					else if (canvas1_data[data_row][data_column] == 1) {	// if it is black
						createPixelObject.data[count_pixel_rgba] = 0;		// r
						createPixelObject.data[count_pixel_rgba+1] = 0;		// g
						createPixelObject.data[count_pixel_rgba+2] = 0;		// b
						createPixelObject.data[count_pixel_rgba+3] = 255;	// a
						count_pixel_rgba += 4;
					}
					else if (canvas1_data[data_row][data_column] == 0) {	// if it is white
						createPixelObject.data[count_pixel_rgba] = 255;		// r
						createPixelObject.data[count_pixel_rgba+1] = 255;		// g
						createPixelObject.data[count_pixel_rgba+2] = 255;		// b
						createPixelObject.data[count_pixel_rgba+3] = 255;	// a
						count_pixel_rgba += 4;
					}
				}
			}
			context1.putImageData( createPixelObject, 0, 0 );
		};
		
		var display_graystyle_canvas1 = function (graystyle_matrix, invert) {
			/* Compute width and height. */
			var graystyle_matrix_height = graystyle_matrix.length;
			var graystyle_matrix_width = graystyle_matrix[1].length;
			
			/* Initialize the imageData. */
			createPixelObject = context.createImageData(graystyle_matrix_width, graystyle_matrix_height);
			
			/* Cycle through the matrix to push into imageData. */
			var count_pixel_rgba = 0;
			for (data_row = 0; data_row < graystyle_matrix_height; data_row++){					// for each row of pixels
				for (data_column = 0; data_column < graystyle_matrix_width; data_column++){		// for each pixel in that row
					var this_graystyle_value = graystyle_matrix[data_row][data_column];			// find the graystyle value (0-255)
					var invert_graystyle_value = 255 - graystyle_matrix[data_row][data_column];			// find the graystyle value (0-255)
					
					if (invert != true) { this_graystyle_value = invert_graystyle_value; }
					
					createPixelObject.data[count_pixel_rgba] = this_graystyle_value;	// r
					createPixelObject.data[count_pixel_rgba+1] = this_graystyle_value;	// g
					createPixelObject.data[count_pixel_rgba+2] = this_graystyle_value;	// b
					createPixelObject.data[count_pixel_rgba+3] = 255;					// a
					count_pixel_rgba += 4;
				}
			}
			context1.putImageData( createPixelObject, 0, 0 );
		}
		
		var display_gray_to_color_canvas1 = function (gray_to_color_matrix) {
			/* Compute width and height. */
			var gray_to_color_matrix_height = gray_to_color_matrix.length;
			var gray_to_color_matrix_width = gray_to_color_matrix[1].length;
			
			/* Initialize the imageData. */
			createPixelObject = context.createImageData(gray_to_color_matrix_width, gray_to_color_matrix_height);
			
			/* Cycle through the matrix to push into imageData. */
			var count_pixel_rgba = 0;
			for (data_row = 0; data_row < gray_to_color_matrix_height; data_row++){					// for each row of pixels
				for (data_column = 0; data_column < gray_to_color_matrix_width; data_column++){		// for each pixel in that row
					var this_graystyle_value = gray_to_color_matrix[data_row][data_column];			// find the graystyle value (0-255)
					var this_gray_to_color_value = { r: 0, g: 0, b: 0 };
					
					if (gray_to_color_matrix == canvas1_sobelEdgeDirMap) {
						if (this_graystyle_value === 0) {
							this_gray_to_color_value = { r: 255, g: 0, b: 0 };
						}else if (this_graystyle_value === 45){
							this_gray_to_color_value = { r: 0, g: 255, b: 0 };
						}else if (this_graystyle_value === 90){
							this_gray_to_color_value = { r: 0, g: 0, b: 255 };
						}else if (this_graystyle_value === 135){
							this_gray_to_color_value = { r: 255, g: 255, b: 0 };
						}else {
							this_gray_to_color_value = { r: 255, g: 0, b: 255 };
						}
					}
					else if (gray_to_color_matrix == canvas1_sobelEdgeGradMap) {
						if (this_graystyle_value < 0) {
							this_gray_to_color_value = { r: 255, g: 0, b: 0 };
						}else if (this_graystyle_value < 200){
							this_gray_to_color_value = { r: 0, g: 255, b: 0 };
						}else if (this_graystyle_value < 400){
							this_gray_to_color_value = { r: 0, g: 0, b: 255 };
						}else if (this_graystyle_value < 600){
							this_gray_to_color_value = { r: 255, g: 255, b: 0 };
						}else if (this_graystyle_value < 800){
							this_gray_to_color_value = { r: 0, g: 255, b: 255 };
						}else {
							this_gray_to_color_value = { r: 255, g: 0, b: 255 };
						}
					}
					else {
						this_gray_to_color_value = { r:85 , g: 170, b: 255 };
					}
					
					createPixelObject.data[count_pixel_rgba] = this_gray_to_color_value.r;		// r
					createPixelObject.data[count_pixel_rgba+1] = this_gray_to_color_value.g;	// g
					createPixelObject.data[count_pixel_rgba+2] = this_gray_to_color_value.b;	// b
					createPixelObject.data[count_pixel_rgba+3] = 255;							// a
					count_pixel_rgba += 4;
				}
			}
			context1.putImageData( createPixelObject, 0, 0 );
		}
		
		var display_coor_list_canvas1 = function (coor_list) {
			/* Check if `coor_list` is actually a singe coordinate: */
			if (typeof(coor_list[0]) == "number") { coor_list = [coor_list]; }
			
			/* Initialize the imageData. */
			createPixelObject = context.createImageData(imageWidth, imageHeight);
			
			/* Create a entirely white image: */
			//var total_pixels = imageWidth * imageHeight;
			//for (var pixel = 0; pixel < total_pixels*4; pixel++) { createPixelObject.data[pixel] = 0; }
			
			/* Change each coordinate in list to black: */
			for (var coor = 0; coor < coor_list.length; coor++) {
				var y = coor_list[coor][0];
				var x = coor_list[coor][1];
				var rgba_coor = (y*imageWidth + x)*4;
				
				createPixelObject.data[rgba_coor] = 100;
				createPixelObject.data[rgba_coor+1] = 100;
				createPixelObject.data[rgba_coor+2] = 100;
				createPixelObject.data[rgba_coor+3] = 255;
			}
			
			context1.putImageData( createPixelObject, 0, 0 );
		}
		
		var display_edge_match_canvas1 = function (c, t, o, l) {
			//run_ocr_on_image();
			// c: index of character.
			// t: index of template.
			// o: [x, y, s] template offset and scale.
			// l: [[ci, ti], ...] pair list of character to template.
			
			/* Initialize the imageData. */
			canvas1.height = imageHeight+500;
			createPixelObject = context1.createImageData(imageWidth, imageHeight+500);
			
			/* Create points and a line for each histogram pair: */
			//for (var p of l) {
			for (var pi = 0; pi < l.length; pi+=1) {
				var p = l[pi];
				var rgba_coor, ls, le;
				/* Find the coordinates of the two points: */
				var ci = p[0];
				var	ti = p[1];
				var	cx = canvas1_limitedNumberEdges[c][ci][1];
				var	cy = canvas1_limitedNumberEdges[c][ci][0];
				var	tx = letterTemplatesEdges[t][ti][1];
				var	ty = letterTemplatesEdges[t][ti][0];
				
				/* Adjust the location of the template character to display: */
				ty = Math.round(o[2]*ty) + o[1];
				tx = Math.round(o[2]*tx) + o[0];
				cy = Math.round(o[3]*cy);
				cx = Math.round(o[3]*cx);
				
				/* Find the slope between the points: */
				var m = (ty-cy)/(tx-cx);
				
				/* Plot the character point as red: */
				rgba_coor = (cy*imageWidth + cx)*4;
				createPixelObject.data[rgba_coor]   = 200;
				createPixelObject.data[rgba_coor+1] = 10;
				createPixelObject.data[rgba_coor+2] = 10;
				createPixelObject.data[rgba_coor+3] = 255;
				
				/* Plot the template point as blue: */
				rgba_coor = (ty*imageWidth + tx)*4;
				createPixelObject.data[rgba_coor]   = 10;
				createPixelObject.data[rgba_coor+1] = 10;
				createPixelObject.data[rgba_coor+2] = 200;
				createPixelObject.data[rgba_coor+3] = 255;
				
				/* Determine where to start the line: */
				if (cx < tx)		{ var ls = cx+1, le = tx-1; }
				else if (cx < tx)	{ var ls = tx+1, le = cx-1; }
				else { continue; }
				
				for (var lx = ls; lx <= le; lx+=1) {
					/* Find y via point slope form, then plot as green: */
					var ly = Math.round( m*(lx - cx) + cy );
					rgba_coor = (ly*imageWidth + lx)*4;
					createPixelObject.data[rgba_coor]   = 10;
					createPixelObject.data[rgba_coor+1] = 200;
					createPixelObject.data[rgba_coor+2] = 10;
					createPixelObject.data[rgba_coor+3] = 255;
				}
			}
			
			context1.mozImageSmoothingEnabled = false;
			context1.msImageSmoothingEnabled = false;
			context1.imageSmoothingEnabled = false;
			context1.putImageData( createPixelObject, 0, 0 );
			//context1.translate(0.5, 0.5);
		};
		
		/* ~~~~~~~~~~~ HELPER FUNCTIONS: ~~~~~~~~~~~ **/
		function copy_array(o) {
			var out, v, key;
			out = [];
			//out = Array.isArray(o) ? [] : {};
			
			for (key in o) {
				v = o[key];
				out[key] = (typeof v === "object") ? copy_array(v) : v;
			}
			return out;
		}
		
		/* ~~~~~~~~~~~ TEMPLATE MATCHING METHOD:" ~~~~~~~~~~~ **/
		
		var removeBorder = function() {
			/* calculate the TOP border */
			for (data_row = 0; data_row < imageHeight; data_row++){	// for each row of pixels
				var totalBlackInRow = 0;
				// determine number of black pixels in row
				for (data_column = 0; data_column < imageWidth; data_column++){	// for each pixel in that row
					if (canvas1_data[data_row][data_column] == 1) { totalBlackInRow+=1; }
				}
				// determine if row is mostly black. if it is, make the whole row white
				if (totalBlackInRow/imageWidth > 0.65) {	// if 80% are black then it is still a border
					for (data_column = 0; data_column < imageWidth; data_column++){	// for each pixel in that row
						canvas1_data[data_row][data_column] = 0;		// make it all white
					}
				}
				else {
					top_border = data_row;	// store the top border
					break;
				}
			}
			
			/* calculate the BOTTOM border */
			for (data_row = imageHeight-1; data_row >= top_border; data_row--){	// for each row of pixels, from bottom to up
				var totalBlackInRow = 0;
				// determine number of black pixels in row
				for (data_column = 0; data_column < imageWidth; data_column++){	// for each pixel in that row
					if (canvas1_data[data_row][data_column] == 1) { totalBlackInRow+=1; }
				}
				// determine if row is mostly black. if it is, make the whole row white
				if (totalBlackInRow/imageWidth > 0.65) {	// if 80% are black then it is still a border
					for (data_column = 0; data_column < imageWidth; data_column++){	// for each pixel in that row
						canvas1_data[data_row][data_column] = 0;		// make it all white
					}
				}
				else {
					bottom_border = imageHeight - data_row - 1;	// store the bottom border
					break;
				}
			}
			
			/* calculate the LEFT border */
			for (data_column = 0; data_column < imageWidth; data_column++){	// for each column of pixels excluding top and bottom border
				var totalBlackInColumn = 0;
				// determine number of black pixels in column
				for (data_row = top_border; data_row < imageHeight-bottom_border; data_row++){	// for each pixel in that column
					if (canvas1_data[data_row][data_column] == 1) {totalBlackInColumn+=1; }
				}
				// determine if column is mostly black. if it is, make the whole column white
				if (totalBlackInColumn/(imageHeight - top_border - bottom_border) > 0.65) {	// if 80% are black then it is still a border
					for (data_row = top_border; data_row < imageHeight-bottom_border; data_row++){	// for each pixel in that column
						canvas1_data[data_row][data_column] = 0;		// make it all white
					}
				}
				else {
					left_border = data_column;	// store the left border
					break;
				}
			}
			
			/* calculate the RIGHT border */
			for (data_column = imageWidth-1; data_column >= left_border; data_column--){	// for each row of pixels, from right to left
				var totalBlackInColumn = 0;
				// determine number of black pixels in column
				for (data_row = top_border; data_row < imageHeight-bottom_border; data_row++){	// for each pixel in that column
					if (canvas1_data[data_row][data_column] == 1) {totalBlackInColumn+=1; }
				}
				// Determine if column is mostly black. If it is, make the whole column white
				if (totalBlackInColumn/(imageHeight - top_border - bottom_border) > 0.65) {	// if 80% are black then it is still a border
					for (data_row = top_border; data_row < imageHeight-bottom_border; data_row++){	// for each pixel in that row
						canvas1_data[data_row][data_column] = 0;		// make it all white
					}
				}
				else {
					right_border = imageWidth - data_column - 1;	// store the right border
					break;
				}
			}
		};
		
		var fixLargePixelNoise = function (largePixelGain) {
			for (data_row = 1; data_row < imageHeight-1; data_row++){	// for each row of pixels (not edges)
				for (data_column = 2; data_column < imageWidth-2; data_column++){	// for each pixel in that row (not edges)
					if (canvas1_data[data_row][data_column] == 1) {
						var countAdjacentBlack = 1;	// this box is black
						
						if (canvas1_data[data_row-1][data_column-2] == 1) { countAdjacentBlack+=1; }	// (-2,-1)
						if (canvas1_data[data_row-1][data_column-1] == 1) { countAdjacentBlack+=1; }	// (-1,-1)
						if (canvas1_data[data_row-1][data_column] == 1) { countAdjacentBlack+=1; }	// (0, -1)
						if (canvas1_data[data_row-1][data_column+1] == 1) { countAdjacentBlack+=1; }	// (1, -1)
						if (canvas1_data[data_row-1][data_column+2] == 1) { countAdjacentBlack+=1; }	// (2, -1)
						
						if (canvas1_data[data_row][data_column-2] == 1) { countAdjacentBlack+=1; }	// (-2, 0)
						if (canvas1_data[data_row][data_column-1] == 1) { countAdjacentBlack+=1; }	// (-1, 0)
																						// this box    (0,  0)
						if (canvas1_data[data_row][data_column+1] == 1) { countAdjacentBlack+=1; }	// (1,  0)
						if (canvas1_data[data_row][data_column+2] == 1) { countAdjacentBlack+=1; }	// (2,  0)
						
						if (canvas1_data[data_row+1][data_column-2] == 1) { countAdjacentBlack+=1; }	// (-2, 1)
						if (canvas1_data[data_row+1][data_column-1] == 1) { countAdjacentBlack+=1; }	// (-1, 1)
						if (canvas1_data[data_row+1][data_column] == 1) { countAdjacentBlack+=1; }	// (0,  1)
						if (canvas1_data[data_row+1][data_column+1] == 1) { countAdjacentBlack+=1; }	// (1,  1)
						if (canvas1_data[data_row+1][data_column+2] == 1) { countAdjacentBlack+=1; }	// (2,  1)
						
						if (countAdjacentBlack >= largePixelGain) {
							canvas1_data[data_row][data_column] = 0;	// white
							for (var i = -1; i <= 1; i++) {
								for (var j = -2; j <= 2; j++) {
									canvas1_data[data_row+i][data_column+j] = 0;	// white
								}
							}
						}
					}
				}
			}
		};
		
		var fixSinglePixelNoise = function (singlePixelGain) {
			for (data_row = 2; data_row < imageHeight-2; data_row++){	// for each row of pixels (not edges)
				for (data_column = 2; data_column < imageWidth-2; data_column++){	// for each pixel in that row (not edges)
					var countAdjacentBlack = 0;
					if (canvas1_data[data_row-1][data_column] == 1) { countAdjacentBlack+=1; }	// N
					if (canvas1_data[data_row][data_column+1] == 1) { countAdjacentBlack+=1; }	// E
					if (canvas1_data[data_row+1][data_column] == 1) { countAdjacentBlack+=1; }	// S
					if (canvas1_data[data_row][data_column-1] == 1) { countAdjacentBlack+=1; }	// W
					
					if (countAdjacentBlack >= 3) {
						canvas1_data[data_row][data_column] = 1;	// black
					}
					else if (countAdjacentBlack == 0) {
						canvas1_data[data_row][data_column] = 0;	// white
					}
					
					var countAdjacentBlack = 0;
					if (canvas1_data[data_row-1][data_column] == 1) { countAdjacentBlack+=2; }	// N
					if (canvas1_data[data_row-1][data_column+1] == 1) { countAdjacentBlack+=1; }	// NE
					if (canvas1_data[data_row][data_column+1] == 1) { countAdjacentBlack+=2; }	// E
					if (canvas1_data[data_row+1][data_column+1] == 1) { countAdjacentBlack+=1; }	// SE
					if (canvas1_data[data_row+1][data_column] == 1) { countAdjacentBlack+=2; }	// S
					if (canvas1_data[data_row+1][data_column-1] == 1) { countAdjacentBlack+=1; }	// SW
					if (canvas1_data[data_row][data_column-1] == 1) { countAdjacentBlack+=2; }	// W
					if (canvas1_data[data_row-1][data_column-1] == 1) { countAdjacentBlack+=1; }	// NW
					
					if (canvas1_data[data_row-2][data_column] == 1) { countAdjacentBlack+=1; }	// NN
					if (canvas1_data[data_row-2][data_column+1] == 1) { countAdjacentBlack+=0.5; }	// NNE
					if (canvas1_data[data_row-2][data_column+2] == 1) { countAdjacentBlack+=1; }	// NNEE
					if (canvas1_data[data_row-1][data_column+2] == 1) { countAdjacentBlack+=0.5; }	// NEE
					if (canvas1_data[data_row][data_column+2] == 1) { countAdjacentBlack+=1; }	// EE
					if (canvas1_data[data_row+1][data_column+2] == 1) { countAdjacentBlack+=0.5; }	// SEE
					if (canvas1_data[data_row+2][data_column+2] == 1) { countAdjacentBlack+=1; }	// SSEE
					if (canvas1_data[data_row+2][data_column+1] == 1) { countAdjacentBlack+=0.5; }	// SSE
					if (canvas1_data[data_row+2][data_column] == 1) { countAdjacentBlack+=1; }	// SS
					if (canvas1_data[data_row+2][data_column-1] == 1) { countAdjacentBlack+=0.5; }	// SSW
					if (canvas1_data[data_row+2][data_column-2] == 1) { countAdjacentBlack+=1; }	// SSWW
					if (canvas1_data[data_row+1][data_column-2] == 1) { countAdjacentBlack+=0.5; }	// SWW
					if (canvas1_data[data_row][data_column-2] == 1) { countAdjacentBlack+=1; }	// WW
					if (canvas1_data[data_row-1][data_column-2] == 1) { countAdjacentBlack+=0.5; }	// NWW
					if (canvas1_data[data_row-2][data_column-2] == 1) { countAdjacentBlack+=1; }	// NNWW
					if (canvas1_data[data_row-2][data_column-1] == 1) { countAdjacentBlack+=0.5; }	// NNW
					
					if (countAdjacentBlack >= 14) {
						canvas1_data[data_row][data_column] = 1;	// black
					}
					else if (countAdjacentBlack <= singlePixelGain) {
						canvas1_data[data_row][data_column] = 0;	// white
					}
				}
			}
		};
		
		var gradientFilter = function (gradientGain) {
			// calculate the gradient for each pixel: except the outer border pixels
			for (data_row = 0; data_row < imageHeight-1; data_row++){	// for each row of pixels
				canvas1_gradient[data_row] = [];
				for (data_column = 0; data_column < imageWidth-1; data_column++){	// for each pixel in that row
					// this calculation is assuming the adjacent pixels are lighter than this one. if darker then make it the average
					if (data_row == 0 || data_column == 0 || data_row == imageHeight-1 || data_column == imageWidth-1){
						canvas1_gradient[data_row][data_column] = 0;
					}
					else {
						//canvas1_gradient[data_row][data_column] = (/*North=*/(canvas1_cost[data_row][data_column] - canvas1_cost[data_row-1][data_column])*(canvas1_cost[data_row][data_column] - canvas1_cost[data_row-1][data_column]) ) + (/*East=*/(canvas1_cost[data_row][data_column] - canvas1_cost[data_row][data_column+1])*(canvas1_cost[data_row][data_column] - canvas1_cost[data_row][data_column+1]) ) + (/*South=*/(canvas1_cost[data_row][data_column] - canvas1_cost[data_row+1][data_column])*(canvas1_cost[data_row][data_column] - canvas1_cost[data_row+1][data_column]) ) + (/*West=*/(canvas1_cost[data_row][data_column] - canvas1_cost[data_row][data_column-1])*(canvas1_cost[data_row][data_column] - canvas1_cost[data_row][data_column]-1) );
						canvas1_gradient[data_row][data_column] = (/*North=*/(canvas1_rgb[data_row][data_column][0] - canvas1_rgb[data_row-1][data_column][0])*(canvas1_rgb[data_row][data_column][0] - canvas1_rgb[data_row-1][data_column][0]) ) + (/*East=*/(canvas1_rgb[data_row][data_column][0] - canvas1_rgb[data_row][data_column+1][0])*(canvas1_rgb[data_row][data_column][0] - canvas1_rgb[data_row][data_column+1][0]) ) + (/*South=*/(canvas1_rgb[data_row][data_column][0] - canvas1_rgb[data_row+1][data_column][0])*(canvas1_rgb[data_row][data_column][0] - canvas1_rgb[data_row+1][data_column][0]) ) + (/*West=*/(canvas1_rgb[data_row][data_column][0] - canvas1_rgb[data_row][data_column-1][0])*(canvas1_rgb[data_row][data_column][0] - canvas1_rgb[data_row][data_column-1][0]) );
						if (canvas1_gradient[data_row][data_column] > gradientGain*gradientGain) {	// if true, then it is noisy in respect to the adjacent pixels so delete the pixel (white)
							//if (  ) {
							//	canvas1_data[data_row][data_column] = 0;	// black
							//}
						}
						else {
							//canvas1_data[data_row][data_column] = 1;	// black
						}
					}
				}
			}
			
			// check gradient against adjacent pixels for each pixel: except the outer border pixels
			//var gradient_cost;
			//for (data_row = 1; data_row < imageHeight-2; data_row++){	// for each row of pixels
			//	for (data_column = 1; data_column < imageWidth-2; data_column++){	// for each pixel in that row
			//		// this calculation is assuming the adjacent pixels are lighter than this one. if darker then make it the average
			//		gradient_cost = (/*North=*/(canvas1_gradient[data_row][data_column] - canvas1_gradient[data_row-1][data_column])*(canvas1_gradient[data_row][data_column] - canvas1_gradient[data_row-1][data_column]) ) + (/*East=*/(canvas1_gradient[data_row][data_column] - canvas1_gradient[data_row][data_column+1])*(canvas1_gradient[data_row][data_column] - canvas1_gradient[data_row][data_column+1]) ) + (/*South=*/(canvas1_gradient[data_row][data_column] - canvas1_gradient[data_row+1][data_column])*(canvas1_gradient[data_row][data_column] - canvas1_gradient[data_row+1][data_column]) ) + (/*West=*/(canvas1_gradient[data_row][data_column] - canvas1_gradient[data_row][data_column-1])*(canvas1_gradient[data_row][data_column] - canvas1_gradient[data_row][data_column]-1) );
			//		if (gradient_cost < gradientGain*gradientGain) {	// if true, then it is noisy in respect to the adjacent pixels so delete the pixel (white)
			//			canvas1_data[data_row][data_column] = 0;	// black
			//		}
			//		else {
			//			//canvas1_data[data_row][data_column] = 1;	// black
			//		}
			//	}
			//}
		};
		
		/* Compares nearby pixels and see if those pixels are significantly darker (text) */
		var colorCompareFilter = function (compareGain) {
			for (data_row = 2; data_row < imageHeight-2; data_row++){	// for each row of pixels (not next to edges)
				for (data_column = 2; data_column < imageWidth-2; data_column++){	// for each pixel in that row (not next to edges)
					
					var countAdjacentDarker = 0;
					// if this rgb are less than adjacent r,g,b 
					
					
					if ( canvas1_rgb[data_row][data_column][0] > canvas1_rgb[data_row-1][data_column][0] + compareGain && canvas1_rgb[data_row][data_column][1] > canvas1_rgb[data_row-1][data_column][1] + compareGain && canvas1_rgb[data_row][data_column][1] > canvas1_rgb[data_row-1][data_column][1] + compareGain ) {
						if ( canvas1_rgb[data_row][data_column][0] > canvas1_rgb[data_row-2][data_column][0] + compareGain*2 && canvas1_rgb[data_row][data_column][1] > canvas1_rgb[data_row-2][data_column][1] + compareGain*2 && canvas1_rgb[data_row][data_column][1] > canvas1_rgb[data_row-2][data_column][1] + compareGain*2 ) {
							countAdjacentDarker += 2;	// NN and N
						} else {
							countAdjacentDarker += 1;	// N
						}
					}
					
					if ( canvas1_rgb[data_row][data_column][0] > canvas1_rgb[data_row+1][data_column][0] + compareGain && canvas1_rgb[data_row][data_column][1] > canvas1_rgb[data_row+1][data_column][1] + compareGain && canvas1_rgb[data_row][data_column][1] > canvas1_rgb[data_row+1][data_column][1] + compareGain ) {
						if ( canvas1_rgb[data_row][data_column][0] > canvas1_rgb[data_row+2][data_column][0] + compareGain*2 && canvas1_rgb[data_row][data_column][1] > canvas1_rgb[data_row+2][data_column][1] + compareGain*2 && canvas1_rgb[data_row][data_column][1] > canvas1_rgb[data_row+2][data_column][1] + compareGain*2 ) {
							countAdjacentDarker += 2;	// SS and S
						} else {
							countAdjacentDarker += 1;	// S
						}
					}
					
					if ( canvas1_rgb[data_row][data_column][0] > canvas1_rgb[data_row][data_column+1][0] + compareGain && canvas1_rgb[data_row][data_column][1] > canvas1_rgb[data_row][data_column+1][1] + compareGain && canvas1_rgb[data_row][data_column][1] > canvas1_rgb[data_row][data_column+1][1] + compareGain ) {
						if ( canvas1_rgb[data_row][data_column][0] > canvas1_rgb[data_row][data_column+2][0] + compareGain*2 && canvas1_rgb[data_row][data_column][1] > canvas1_rgb[data_row][data_column+2][1] + compareGain*2 && canvas1_rgb[data_row][data_column][1] > canvas1_rgb[data_row][data_column+2][1] + compareGain*2 ) {
							countAdjacentDarker += 2;	// EE and E
						} else {
							countAdjacentDarker += 1;	// E
						}
					}
					
					if ( canvas1_rgb[data_row][data_column][0] > canvas1_rgb[data_row][data_column-1][0] + compareGain && canvas1_rgb[data_row][data_column][1] > canvas1_rgb[data_row][data_column-1][1] + compareGain && canvas1_rgb[data_row][data_column][1] > canvas1_rgb[data_row][data_column-1][1] + compareGain ) {
						if ( canvas1_rgb[data_row][data_column][0] > canvas1_rgb[data_row][data_column-2][0] + compareGain*2 && canvas1_rgb[data_row][data_column][1] > canvas1_rgb[data_row][data_column-2][1] + compareGain*2 && canvas1_rgb[data_row][data_column][1] > canvas1_rgb[data_row][data_column-2][1] + compareGain*2 ) {
							countAdjacentDarker += 2;	// WW and W
						} else {
							countAdjacentDarker += 1;	// W
						}
					}
					
					if (countAdjacentDarker >= 2) {
						canvas1_data[data_row][data_column] = 0;	// white
					}
					else if (countAdjacentDarker <= 1) {
						//canvas1_data[data_row][data_column] = 0;	// black
					}
				}
			}
		};
		
		var convertToBW = function(colorGain, costGain) {
			var rGain = 0; var gGain = 0; var bGain = 0;
			//var rGain = 109; var gGain = 92.5; var bGain = 79.5;
			//var rgbOffset = [109, 92.5, 79.5];
		//	var rgbOffset = [0, 0, 0];
			//var rgbNoise = [20, 20.5, 15.5];
		//	var rgbNoise = [135, 120, 100];
			for (var pH=0; pH < imageHeight; pH++){
				pixelImageData = context.getImageData(0, pH, imageWidth, 1).data;
				createPixelObject = context.createImageData(imageWidth,1);		// create an imageData for a row
				canvas1_data[pH] = [];
		//		canvas1_cost[pH] = [];
				canvas1_rgb[pH] = [];
				
				for (var pW=0; pW < imageWidth; pW++){	// width
					pixel_D = [];		// stores that pixel's data, which will be processed, then stored
					var pW_array = pW*4;	// stores that pixel's location within array of rgba for many pixels (each 'r' 'g' 'b' and 'a' is a separate value)
					pixel_D[0] = pixelImageData[pW_array];		// r
					pixel_D[1] = pixelImageData[pW_array+1];	// g
					pixel_D[2] = pixelImageData[pW_array+2];	// b
					pixel_D[3] = pixelImageData[pW_array+3];	// a
					
					canvas1_rgb[pH][pW] = [pixel_D[0], pixel_D[1], pixel_D[2]];	// stores true rgb values for each pixel
					// 
					//canvas1_cost[pH][pW] = [];
					//canvas1_cost[pH][pW][0] = (pixel_D[0] - rGain)*(pixel_D[0] - rGain);
		//			var estimate_vector = [];
		//			estimate_vector[0] = canvas1_rgb[pH][pW][0] - rgbOffset[0];
		//			estimate_vector[1] = canvas1_rgb[pH][pW][1] - rgbOffset[1];
		//			estimate_vector[2] = canvas1_rgb[pH][pW][2] - rgbOffset[2];
					
		//			var rgb_noise = rgbNoise[0]*rgbNoise[0] + rgbNoise[1]*rgbNoise[1] + rgbNoise[2]*rgbNoise[2];
		//			var rgb_cost = estimate_vector[0]*estimate_vector[0] + estimate_vector[1]*estimate_vector[1] + estimate_vector[2]*estimate_vector[2];
		//			canvas1_cost[pH][pW] = rgb_cost - rgb_noise;
					
					//canvas1_cost[pH][pW] = (canvas1_rgb[pH][pW][0] - rgbOffset[0])*(canvas1_rgb[pH][pW][0] - rgbOffset[0]) + (canvas1_rgb[pH][pW][1] - rgbOffset[1])*(canvas1_rgb[pH][pW][1] - rgbOffset[1]) + (canvas1_rgb[pH][pW][2] - rgbOffset[2])*(canvas1_rgb[pH][pW][2] - rgbOffset[2]);
					//canvas1_cost[pH][pW] = (pixel_D[0] - rGain)*(pixel_D[0] - rGain) + (pixel_D[1] - gGain)*(pixel_D[1] - gGain) + (pixel_D[2] - bGain)*(pixel_D[2] - bGain);
					
					/* uses magnitude to determine if black or white (r^2 + g^2 + b^2 < gain^2). note: squaring via multiplication is significantly faster. */
		//			if (canvas1_cost[pH][pW] < costGain*costGain ) {
		//				pixel_D[0] = 0;
		//				pixel_D[1] = 0;
		//				pixel_D[2] = 0;
		//			}
		//			else {
		//				pixel_D[0] = 255;
		//				pixel_D[1] = 255;
		//				pixel_D[2] = 255;
		//			}
					
					/* This limits the rgb colors  */
					if (pixelImageData[pW_array] > rGain+colorGain || pixelImageData[pW_array+1] > gGain+colorGain || pixelImageData[pW_array+2] > bGain+colorGain) {
						canvas1_data[pH][pW] = 0;	// white
					} else {
						canvas1_data[pH][pW] = 1;	// black
					}
					
					// store the value in the canvas's array
		//			if (pixel_D[0] == 0) 		{ canvas1_data[pH][pW] = 1; }	// black
		//			else if (pixel_D[0] == 255)	{ canvas1_data[pH][pW] = 0; }	// white
					
					
				}
			}
		};
		
		var binarizeWithOtsu = function (threshold_shift) {
			/* Initialize histogram. */
			var histogram = [];
			for (var k = 0; k < 256; k++) { histogram[k] = 0; }
			
			for (var pH=0; pH < imageHeight; pH++){
				/* Load the image data and store. This is the only time we need to do this for the image.*/
				pixelImageData = context.getImageData(0, pH, imageWidth, 1).data;
				canvas1_data[pH] = [];
				canvas1_graystyle[pH] = [];
				canvas1_rgb[pH] = [];
				
				for (var pW=0; pW < imageWidth; pW++){	// width
					var pW_array = pW*4;	// stores that pixel's location within array of rgba for many pixels (each 'r' 'g' 'b' and 'a' is a separate value)
					pixel_D = [];			// stores that pixel's rgb data, which will be processed, then stored.
					pixel_D[0] = pixelImageData[pW_array];		// r
					pixel_D[1] = pixelImageData[pW_array+1];	// g
					pixel_D[2] = pixelImageData[pW_array+2];	// b
					pixel_D[3] = pixelImageData[pW_array+3];	// a
					
					canvas1_rgb[pH][pW] = [pixel_D[0], pixel_D[1], pixel_D[2]];	// stores true rgb values for each pixel
					canvas1_graystyle[pH][pW] = Math.round( (0.3*pixel_D[0] + 0.59*pixel_D[1] + 0.11*pixel_D[2]) );
					
					histogram[ canvas1_graystyle[pH][pW] ] += 1;
				}
			}
			
			/* Use the otsu algorithm to compute the Threshold. */
			var image_threshold = otsu(histogram, imageWidth*imageHeight);
			
			/* Use the threshold to binarize the image. */
			for (var pH=0; pH < imageHeight; pH++){
				for (var pW=0; pW < imageWidth; pW++){
					if (canvas1_graystyle[pH][pW] < image_threshold + threshold_shift) {
						canvas1_data[pH][pW] = 1;	// black
					}
					else {
						canvas1_data[pH][pW] = 0;	// white
					}
				}
			}
		};
		
		var otsu = function (histogram, total, return_both_thresholds) {
			var sum = 0;
			for (var i = 1; i < histogram.length; ++i)
				sum += i * histogram[i];
			var sumB = 0;
			var wB = 0;
			var wF = 0;
			var mB;
			var mF;
			var max = 0.0;
			var between = 0.0;
			var threshold1 = 0.0;
			var threshold2 = 0.0;
			for (var i = 0; i < histogram.length; ++i) {
				wB += histogram[i];
				if (wB == 0)
					continue;
				wF = total - wB;
				if (wF == 0)
					break;
				sumB += i * histogram[i];
				mB = sumB / wB;
				mF = (sum - sumB) / wF;
				between = wB * wF * (mB - mF) * (mB - mF);
				if ( between >= max ) {
					threshold1 = i;
					if ( between > max ) {
						threshold2 = i;
					}
					max = between;            
				}
			}
			if (return_both_thresholds == true) { return [threshold1, threshold2]; }
			else { return ( threshold1 + threshold2 ) / 2.0; }
		};
		
		/* Master function for separating each character */
		var separateCharacters = function () {
			/* set the area to determine structure (with the actual row and column number) */
			canvas1_structure = [top_border, (imageHeight - bottom_border - 1), [left_border, (imageWidth - right_border - 1)]];
			
			// limit number of cycles:
			for (var structure_cycles = 0; (structure_cycles < 30 && canvas1_not_stuctured.length > 0); structure_cycles++) {
				/* Divide all unstructured columns into rows: */
				for (var this_sub_structure = 0; this_sub_structure < canvas1_not_stuctured.length; this_sub_structure++) {
					separateCharactersRow( canvas1_not_stuctured[this_sub_structure] );
				}
				
				/* Store all unstructured rows for further structuring: */
				canvas1_not_stuctured = canvas1_temp_not_stuctured.slice(0);
				canvas1_temp_not_stuctured = [];
				
				/* Divide all unstructured rows into columns: */
				for (var this_sub_structure = 0; this_sub_structure < canvas1_not_stuctured.length; this_sub_structure++) {
					separateCharactersColumn( canvas1_not_stuctured[this_sub_structure] );
				}
				
				/* Store all unstructured columns for further structuring: */
				canvas1_not_stuctured = canvas1_temp_not_stuctured.slice(0);
				canvas1_temp_not_stuctured = [];
			}
			
			
			for (var k = 0; k < canvas1_character_location.length; k++) {
				var parent_structureString = "canvas1_structure";
				var this_structureString = "canvas1_structure";
				for (var kk = 0; kk < canvas1_character_location[k].length; kk++) {
					this_structureString += ("[" + canvas1_character_location[k][kk] + "]");
					if (kk < canvas1_character_location[k].length-1) {
						parent_structureString += ("[" + canvas1_character_location[k][kk] + "]");
					}
				}
				
				boxTop = eval(parent_structureString)[0];
				boxBottom = eval(parent_structureString)[1];
				boxLeft = eval(this_structureString)[0];
				boxRight = eval(this_structureString)[1];
				
				for (data_column = boxLeft-1; data_column <= boxRight+1; data_column++){
					if (boxTop !== 0 && data_column < imageWidth && data_column >= 0) { 				canvas1_data[boxTop-1][data_column] = 2; 	}
					if (boxBottom !== imageHeight-1 && data_column < imageWidth && data_column >= 0) {	canvas1_data[boxBottom+1][data_column] = 2;	}
				}
			}
		};
		
		/* Separates each character horizontally by cutting out the rows: */
		var separateCharactersRow = function (locationInStructure) {
			/* counts the number of consecutive empty rows */
			var countEmptyRows = 0;
			var countFilledRows = 0;
			var child_structureArray = [];
			
			/* dynamically create a string which can be evaluated to contain the data for this box to structure: */
			var parent_structureString = "canvas1_structure";
			var this_structureString = "canvas1_structure";
			for (var k = 0; k < locationInStructure.length; k++) {
				if (locationInStructure[k] < 2) { console.log("error in separating rows! location so far is: "+this_structureString); return; }	// Just in case the value is lower than 2, because the script below will throw an error if below 2.
				this_structureString += ("[" + locationInStructure[k] + "]");
				if (k < locationInStructure.length-1) {
					parent_structureString += ("[" + locationInStructure[k] + "]");
				}
			}
			
			/* Set the box's border to structure in. */
			if (locationInStructure.length % 2 == 1) {	// odd: [top, bottom, [left, right]]
				boxTop = eval(parent_structureString)[0];
				boxBottom = eval(parent_structureString)[1];
				boxLeft = eval(this_structureString)[0];
				boxRight = eval(this_structureString)[1];
			}
			else {													// even: [left, right, [top, bottom]]
				boxTop = eval(this_structureString)[0];
				boxBottom = eval(this_structureString)[1];
				boxLeft = eval(parent_structureString)[0];
				boxRight = eval(parent_structureString)[1];
			}
			boxRows = boxBottom-boxTop+1;
			boxColumns = boxRight-boxLeft+1;
			
			/* For each row within the particular structure */
			for (data_row = boxTop; data_row <= boxBottom; data_row++){	// for each row within the area to be analyzed.
				/* initialize the row's variables */
				var totalBlackInRow = 0;
				
				/* find number of black pixels in row */
				for (data_column = boxLeft; data_column <= boxRight; data_column++){	// for each pixel in that row
					if (canvas1_data[data_row][data_column] == 1) { totalBlackInRow+=1; }
				}
				/* determine if row is all white */
				if (totalBlackInRow/boxColumns < 0.001 || data_row == boxBottom) {
					/* This row is all white. */
					countEmptyRows += 1;
					
					if (countFilledRows >= 1 || (data_row == boxBottom && eval(this_structureString)[eval(this_structureString).length-1].length == 1)) {
						/* Then this is a BOTTOM border of some text/black */
						if (data_row == boxBottom) {
							eval(this_structureString)[eval(this_structureString).length-1][1] = data_row;
						} else {
							eval(this_structureString)[eval(this_structureString).length-1][1] = data_row-1;
						}
						
						/* Store this sub-structure in array for further processing/structuring: */
						child_structureArray = locationInStructure.slice(0);
						child_structureArray[ child_structureArray.length ] = eval(this_structureString).length-1;
						
						//if (data_column == boxRight && eval(this_structureString)[eval(this_structureString).length-1][0] == boxLeft) {
						//	canvas1_character_location.push(child_structureArray);
						//}
						//else {
							canvas1_temp_not_stuctured.push(child_structureArray);
						//}
						
						/* TEMP TODO */
						if (debugMode && locationInStructure.length > 0){ for (data_column = boxLeft; data_column <= boxRight; data_column++){	// for each pixel in row
							canvas1_data[data_row][data_column] = 2;		// make it gray
						} }
					}
					countFilledRows = 0;
				}
				else {
					/* This row contains black. */
					/* If previous rows were white (and this one is black) OR this is the first row (and it is black) */
					if (countEmptyRows >= 1 || data_row <= boxTop) {
						/* Then this is a top border of some text/black */
						eval(this_structureString).push([data_row]);
						
						/* TEMP TODO */
						if(debugMode && data_row !== 0 && locationInStructure.length > 0){ for (data_column = boxLeft; data_column <= boxRight; data_column++){	// for each pixel in row before
							canvas1_data[data_row-1][data_column] = 2;		// make it gray
						} }
					}
					
					/* reset the count of empty rows. */
					countEmptyRows = 0;
					countFilledRows += 1;
				}
			}
		};
		
		/* Separates each character vertically by cutting out the columns: */
		var separateCharactersColumn = function (locationInStructure) {
			/* counts the number of consecutive empty columns */
			var countEmptyColumns = 0;
			var countFilledColumns = 0;
			var child_structureArray = [];
			
			/* dynamically create a string which can be evaluated to contain the data for this box to structure: */
			var parent_structureString = "canvas1_structure";
			var this_structureString = "canvas1_structure";
			for (var k = 0; k < locationInStructure.length; k++) {
				if (locationInStructure[k] < 2) { console.log("error in separating columns! location so far is: "+this_structureString); return; }	// Just in case the value is lower than 2, because the script below will throw an error if below 2.
				this_structureString += ("[" + locationInStructure[k] + "]");
				if (k < locationInStructure.length-1) {
					parent_structureString += ("[" + locationInStructure[k] + "]");
				}
			}
			
			/* Set the box's border to structure in. */
			if (locationInStructure.length % 2 == 1) {	// odd: [top, bottom, [left, right]]
				boxTop = eval(parent_structureString)[0];
				boxBottom = eval(parent_structureString)[1];
				boxLeft = eval(this_structureString)[0];
				boxRight = eval(this_structureString)[1];
			}
			else {													// even: [left, right, [top, bottom]]
				boxTop = eval(this_structureString)[0];
				boxBottom = eval(this_structureString)[1];
				boxLeft = eval(parent_structureString)[0];
				boxRight = eval(parent_structureString)[1];
			}
			boxRows = boxBottom-boxTop+1;
			boxColumns = boxRight-boxLeft+1;
			
			if (this_structureString == "canvas1_structure[2][4][17][3][2][2]") {
				var asdf = "debug";
			}
			
			/* Search each column within the box specified */
			for (data_column = boxLeft; data_column <= boxRight; data_column++){	// for each column within the area to be analyzed.
				/* initialize the row's variables */
				var totalBlackInColumn = 0;
				
				/* find number of black pixels in column */
				for (data_row = boxTop; data_row <= boxBottom; data_row++){	// for each pixel in that column within the set of rows to find structure
					if (canvas1_data[data_row][data_column] == 1) { totalBlackInColumn+=1; }
				}
				/* determine if column is all white */
				if (totalBlackInColumn/boxRows < 0.001 || (data_column >= boxRight && eval(this_structureString)[eval(this_structureString).length-1].length == 1) ) {
					/* This column is all white. */
					countEmptyColumns += 1;
					
					if (countFilledColumns >= 1) {
						/* Then this is a RIGHT border of some text/black. */
						if (data_column == boxRight) {
							eval(this_structureString)[eval(this_structureString).length-1][1] = data_column;
						} else {
							eval(this_structureString)[eval(this_structureString).length-1][1] = data_column-1;
						}
						
						/* Store this sub-structure in array for further processing/structuring: */
						child_structureArray = locationInStructure.slice(0);
						child_structureArray[ child_structureArray.length ] = eval(this_structureString).length-1;
						
						if (data_column == boxRight && eval(this_structureString)[eval(this_structureString).length-1][0] == boxLeft) {
							canvas1_character_location.push(child_structureArray);
						}
						else {
							canvas1_temp_not_stuctured.push(child_structureArray);
						}
						
						/* TEMP TODO */
						if (debugMode && locationInStructure.length > 1){ for (data_row = boxTop; data_row <= boxBottom; data_row++){	// for each pixel in that column
							canvas1_data[data_row][data_column] = 2;		// make it gray
						}}
					}
					countFilledColumns = 0;
				}
				else {
					/* This column contains black. */
					/* If previous columns were white (and this one is black) OR this is the first column (and it is black) */
					if (countEmptyColumns >= 1 || data_column <= boxLeft) {
						/* Then this is a LEFT border of some text/black. Make a new sub-array. */
						eval(this_structureString).push([data_column]);
						
						/* TEMP TODO */
						if(debugMode && data_column !== 0 && locationInStructure.length > 1){ for (data_row = boxTop; data_row <= boxBottom; data_row++){	// for each pixel in row before
							canvas1_data[data_row][data_column-1] = 2;		// make it gray
						} }
					}
					
					/* reset the count of empty rows. */
					countEmptyColumns = 0;
					countFilledColumns += 1;
				}
			}
		};
		
		/* Sorts the location array so the character recognition goes in order of letter, not structure itself. */
		var orderCharactersByStructure = function () {
			/* Make a custom sorting function for the native `.sort()` */
			canvas1_character_location.sort(function(a, b) {
				// Find the minimum length of the two values compared, (like [2, 3] and [2, 3, 5]).
				// This shouldn't happen in our code but we'll include it anyway.
				var minSortlength = Math.min(a.length, b.length);
				// Compare each value, beginning from index 0.
				for (var sortIndex = 0; sortIndex < minSortlength; sortIndex++) {
					if ( a[sortIndex] < b[sortIndex] ) { return -1; }
					if ( a[sortIndex] > b[sortIndex] ) { return 1; }
				}
				// If the two are the same value, then the shorter one comes first.
				return a.length - b.length;
			});
		};
		
		/* Generates the templates for the template matching (character recognition): */
		var generateTemplates = function () {
			if (select_image_name == "font_arial1.jpg") {
				var imageKnownCharacters = ["a","b","c","d","e","f","g","h",".","i","j","k","l","m","n","o","p","q","r","s","t","u","v","w","x","y","z","A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z","0","1","2","3","4","5","6","7","8","9","+","-","/","*","(",")","&","^","#","{","}","?","<",">","{function_symbol}","{not_equal}","{thin_x}","{theta}","{pi}","-",".","{square_root_symbol}","{angle_symbol}","{summation_symbol}","{empty_set_character}","{phi}",","];
			}
			else if (select_image_name == "font_arial2.jpg") {
				var imageKnownCharacters = ["a","b","c","d","e","f","g","h",".","i","j","k","l","m","n","o","p","q","r","s","t","u","v","w","x","y","z","A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z","0","1","2","3","4","5","6","7","8","9","+","-","/","*","(",")","&","^","#","{","}","?","<",">","{function_symbol}","{not_equal}","{thin_x}","{theta}","{pi}","-",".","{square_root_symbol}","{angle_symbol}","{summation_symbol}","{empty_set_character}","{phi}",",","a","b","c","d","e","f","g","h",".","i","j","k","l","m","n","o","p","q","r","s","t","u","v","w","x","y","z","0","1","2","3","4","5","6","7","8","9","{theta}","{pi}","a","b","c","d","e","f","g","h",".","i","j","k","l","m","n","o","p","q","r","s","t","u","v","w","x","y","z","0","1","2","3","4","5","6","7","8","9"];
			}
			else if (select_image_name == "font_cambria1.jpg"){
				var imageKnownCharacters = ["1","2","3","4","5","6","7","8","9","0","+","-","/","*","(",")","{","}","<",">","?",".","_","x","y","z","a","b","c",".","i","r","{pi}","{theta}",".","_",".","{multiply}","{square_root}","{function}"];
			}
			else if (select_image_name == "font_cambria2.jpg"){
				var imageKnownCharacters = ["1","2","3","4","5","6","7","8","9","0","+","-","/","*","(",")","{","}","<",">","?",".","_","x","x","y",".","i","r","{pi}","{theta}",".","_",".","{multiply}","{square_root}","{function}"];
			}
			//else if (select_image_name == "font_serif2.jpg") {
			//	var imageKnownCharacters = ["a","b","c","d","e","f","g","h","","i","","j","k","l","m","n","o","p","q","r","s","t","u","v","w","x","y","z","A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z","1","2","3","4","5","6","7","8","9","0"];
			//}
			else { return; }
			letterTemplates = [];
			
			/* For every character, generate a template for it based on its known character. */
			for (var k = 0; k < canvas1_character_location.length; k++) {
				/* Convert the character location into an accessible variable. */
				var parent_structureString = "canvas1_structure";
				var this_structureString = "canvas1_structure";
				for (var kk = 0; kk < canvas1_character_location[k].length; kk++) {
					this_structureString += ("[" + canvas1_character_location[k][kk] + "]");
					if (kk < canvas1_character_location[k].length-1) {
						parent_structureString += ("[" + canvas1_character_location[k][kk] + "]");
					}
				}
				
				/* Determine the borders */
				if (canvas1_character_location[k].length % 2 == 1) {	// odd: [top, bottom, [left, right]]
					boxTop = eval(parent_structureString)[0];
					boxBottom = eval(parent_structureString)[1];
					boxLeft = eval(this_structureString)[0];
					boxRight = eval(this_structureString)[1];
				}
				else {													// even: [left, right, [top, bottom]]
					boxTop = eval(this_structureString)[0];
					boxBottom = eval(this_structureString)[1];
					boxLeft = eval(parent_structureString)[0];
					boxRight = eval(parent_structureString)[1];
				}
				boxRows = boxBottom-boxTop+1;
				boxColumns = boxRight-boxLeft+1;
				
				/* Initialize this character in templates. */
				letterTemplates[k] = [imageKnownCharacters[k], [/*matrix*/], [/* percent black in each row */], [/* percent black in each column */], 0 /* total black characters */, [ [[],[]], [[],[]], [[],[]]/* pixel distribution by sets of columns */] ];
				
				/* Cycle through each pixel in the character location to push into its template. */
				for (data_row = boxTop; data_row <= boxBottom; data_row++){
					/* insert new row into matrix. */
					letterTemplates[k][1].push([]);
					/* cycle through and insert column data. */
					for (data_column = boxLeft; data_column <= boxRight; data_column++){
						/* insert the data of the character into its template. */
						letterTemplates[k][1][ letterTemplates[k][1].length-1 ].push( canvas1_data[data_row][data_column] );
					}
				}
				
				/* For each ROW, determine percentage of black. */
				for (data_row = boxTop; data_row <= boxBottom; data_row++) {
					/* Initialize. */
					var numberBlackInRow = 0;
					/* Count the number of blacks in this row. */
					for (data_column = boxLeft; data_column <= boxRight; data_column++){
						if (canvas1_data[data_row][data_column] == 1) { numberBlackInRow += 1; }
					}
					/* Determine the percentage. */
					letterTemplates[k][2].push( Math.round( numberBlackInRow/boxColumns *1000 )/1000 );
				}
				
				/* For each COLUMN, determine percentage of black. */
				for (data_column = boxLeft; data_column <= boxRight; data_column++){
					/* Initialize. */
					var numberBlackInColumn = 0;
					/* Count the number of blacks in this column. */
					for (data_row = boxTop; data_row <= boxBottom; data_row++) {
						if (canvas1_data[data_row][data_column] == 1) { numberBlackInColumn += 1; }
					}
					/* Determine the percentage. */
					letterTemplates[k][3].push( Math.round( numberBlackInColumn/boxRows *1000 )/1000 ) ;
					letterTemplates[k][4] += numberBlackInColumn;
				}
				
				/* For every group (3 bins) of several columns (dependent of image width), determine its mean (of `y` values) and standard deviation. */
				/* Store each black pixel in its correct bin. */
				var thisTemplateCharacterBins = [ [[],[]], [[],[]], [[],[]] ];
				for (data_column = boxLeft; data_column <= boxRight; data_column++){
					/* Initialize which bin (column). */
					var currentBin = Math.ceil( 3*(data_column-boxLeft+1)/boxColumns )-1;
					
					for (data_row = boxTop; data_row <= boxBottom; data_row++) {
						/* Initialize which sub-bin (row). */
						var currentSubBin = Math.round( (data_row-boxTop+1)/boxRows );
						/* If black, push this point into its bin. */
						if (canvas1_data[data_row][data_column] == 1) { thisTemplateCharacterBins[currentBin][currentSubBin].push( data_row-boxTop+1 ); }
					}
				}
				
				/* For each bin, calculate its mean and standard deviation. */
				for (var thisBin = 0; thisBin < 3; thisBin++){
					for (var thisSubBin = 0; thisSubBin < 2; thisSubBin++){
						/* Initialize. */
						var binMean = 0;
							binSTD = 0;
							thisDifferenceFromMean = 0;
						
						var totalBinIndex = thisTemplateCharacterBins[thisBin][thisSubBin].length;
						/* Add up all y coordinates. Then divide by total number to get the mean (as a percentage of height). */
						for (var subBinIndex = 0; subBinIndex < totalBinIndex; subBinIndex++) {
							binMean += thisTemplateCharacterBins[thisBin][thisSubBin][subBinIndex];
						}
						binMean = (binMean/totalBinIndex);	// addition / total = mean
						if(totalBinIndex==0){binMean=0;}
						
						/* Calculate the standard deviation [(each point - mean)^2]. */
						for (var subBinIndex = 0; subBinIndex < totalBinIndex; subBinIndex++) {
							var thisDifferenceFromMean = ( thisTemplateCharacterBins[thisBin][thisSubBin][subBinIndex] - binMean);
							binSTD += thisDifferenceFromMean*thisDifferenceFromMean;
						}
						binSTD = Math.sqrt(binSTD/(totalBinIndex-1))/boxRows;
						binMean = binMean/boxRows;
						if(totalBinIndex <= 1){binSTD=0;}
						
						/* Store the mean and standard deviation in the `letterTemplates`. */
						letterTemplates[k][5][thisBin][thisSubBin] = [binMean, binSTD];
					}
				}
				
				/*
				var binWidth = boxColumns/5;
				var currentBinColumn = boxLeft;
				if (binWidth < 1) { continue; }
				
				for ( var preciseColumn = boxLeft; preciseColumn <= boxRight; preciseColumn += binWidth ){
					/*
						5:	1-1, 2-2, 3-3, 4-4, 5-5		4/5
										1 - 2.2 - 3.4 - 4.6 - 5.8 - 7		5/5
										 1-2,  3-3,  4-5,  6-6,  7-7
						6:	1-1, 2-2, 3-4, 5-5,	6-6		5/5
						7:	0-1, 2-2, 3-4, 5-5, 6-6		6/5
										1 - 2.2 - 3.4 - 4.6 - 5.8 - 7		6/5
										 1-2,  3-3,  4-5,  6-6,  7-7
										
										0 - 1.2 - 2.4 - 3.6 - 4.8 - 6
										 0-1,  2-2,  3-4,  5-5,  6-6
						
						10:	1-2, 3-4, 5-6, 7-8, 9-10	9/5
						12:	2-3, 4-5, 6-7, 8-11, 12-12	11/5
					* /
					
					//var lowerBinColumn = Math.floor(preciseColumn);
					var lowerBinColumn = currentBinColumn+1;
					var upperBinColumn = Math.round(preciseColumn+binWidth);
					
					if (lowerBinColumn == currentBinColumn) {
						
					}
					
					//data_column = lowerBinColumn;
					currentBinColumn = upperBinColumn;
				}*/
			}
		};
		
		var templateMatching = function () {
			/*	
			 Variables used in this `templateMatching` function:
				k											:	number	:	Which character of input image are we attempting to recognize. location within canvas1_character_location.
				canvas1_character_location					:	array	:	Each character found in input image. Points to location in canvas1_structure. Used here to find the borders of the character we are currently recognizing.
				letterTemplates								:	array	:	Information about each template character. Each index has ["character name", [matrix of pixels], [percent black in each row], [percent black in each column], +(total number of black pixels)].
				k_character									:	number	:	Which template in `letterTemplates` are we attempting to match this (input image) character to.
				canvas1_template_summations					:	array	:	Information about each attempted match. Each character: each template: [summation/cost, k_character, height_template_scale, width_template_scale, difference of ROW percentages, difference of COLUMN percentages,  M - percent of template matched, N - percent of input character matched]
				height_template_scale, width_template_scale	:	number	:	Comparison of character sizes for each attempted match.
				adjustedRowIndex, adjustedColumnIndex		:	number	:	A shortcut for the row/column of a certain pixel in the specific template, which is relative (because of scaling) to the row/column of the input character.
				templatePixelValue							:	number	:	A shortcut for the value of a certain pixel in the specific template. Black or white (1 or 0).
				possibleBestSummations						:	array	:	Same as `canvas1_template_summations` except this only includes template matches which are possible matches.
				k_summation									:	number	:	Used to cycle within `possibleBestSummations` (to rule out possibilities, find lowest summation, etc).
				lowestSummationIndex						:	array	:	Contains one index of `canvas1_template_summations` containing the LOWEST SUMMATION found in `possibleBestSummations`.
				bestMatchTemplate							:	array	:	Contains one index of `canvas1_template_summations` containing the BEST MATCH found in `possibleBestSummations`.
				outputText									:	array	:	One value per character. When put together, it is the final text recognized and outputted by the OCR.
			 */
			
			/* for every character, determine which character it most closely represents based on a template matching system. */
			for (var k = 0; k < canvas1_character_location.length; k++) {
				/* convert the character location into an accessible variable. */
				var parent_structureString = "canvas1_structure";
				var this_structureString = "canvas1_structure";
				for (var kk = 0; kk < canvas1_character_location[k].length; kk++) {
					this_structureString += ("[" + canvas1_character_location[k][kk] + "]");
					if (kk < canvas1_character_location[k].length-1) {
						parent_structureString += ("[" + canvas1_character_location[k][kk] + "]");
					}
				}
				
				/* Determine the borders. */
				if (canvas1_character_location[k].length % 2 == 1) {	// odd: [top, bottom, [left, right]]
					boxTop = eval(parent_structureString)[0];
					boxBottom = eval(parent_structureString)[1];
					boxLeft = eval(this_structureString)[0];
					boxRight = eval(this_structureString)[1];
				}
				else {													// even: [left, right, [top, bottom]]
					boxTop = eval(this_structureString)[0];
					boxBottom = eval(this_structureString)[1];
					boxLeft = eval(parent_structureString)[0];
					boxRight = eval(parent_structureString)[1];
				}
				boxRows = boxBottom-boxTop+1;
				boxColumns = boxRight-boxLeft+1;
				
				/* Remove characters less than 3 pixels wide or tall. */
				if (boxRows <= 1 || boxColumns <= 1) {
					canvas1_character_location.splice(k,1);  k-=1;  continue;
				}
				
				/* Initialize percentage black. */
				canvas1_character_black_percent[k] = [[],[],0];
				/* For each ROW, determine percentage of black. */
				for (data_row = boxTop; data_row <= boxBottom; data_row++) {
					/* Initialize. */
					var numberBlackInRow = 0;
					/* Count the number of blacks in this row. */
					for (data_column = boxLeft; data_column <= boxRight; data_column++){
						if (canvas1_data[data_row][data_column] == 1) { numberBlackInRow += 1; }
					}
					/* Determine the percentage. */
					canvas1_character_black_percent[k][0].push( Math.round( numberBlackInRow/boxColumns *1000 )/1000 );
				}
				
				/* For each COLUMN, determine percentage of black. */
				for (data_column = boxLeft; data_column <= boxRight; data_column++){
					/* Initialize. */
					var numberBlackInColumn = 0;
					/* Count the number of blacks in this column. */
					for (data_row = boxTop; data_row <= boxBottom; data_row++) {
						if (canvas1_data[data_row][data_column] == 1) { numberBlackInColumn += 1; }
					}
					/* Determine the percentage. */
					canvas1_character_black_percent[k][1].push( Math.round( numberBlackInColumn/boxRows *1000 )/1000 ) ;
					canvas1_character_black_percent[k][2] += numberBlackInColumn;
				}
				
				/* Initialize zoning image and mean/STD calculation. */
				canvas1_zoning_properties = [ [[],[]], [[],[]], [[],[]] ];
				var thisTemplateCharacterBins = [ [[],[]], [[],[]], [[],[]] ];
				/* Store each black pixel in its correct bin. */
				for (data_column = boxLeft; data_column <= boxRight; data_column++){
					/* Initialize which bin (column). */
					var currentBin = Math.ceil( 3*(data_column-boxLeft+1)/boxColumns )-1;
					
					for (data_row = boxTop; data_row <= boxBottom; data_row++) {
						/* Initialize which sub-bin (row). */
						var currentSubBin = Math.round( (data_row-boxTop+1)/boxRows );
						/* If black, push this point into its bin. */
						if (canvas1_data[data_row][data_column] == 1) { thisTemplateCharacterBins[currentBin][currentSubBin].push( data_row-boxTop+1 ); }
					}
				}
				
				/* For each bin, calculate its mean and standard deviation. */
				for (var thisBin = 0; thisBin < 3; thisBin++){
					for (var thisSubBin = 0; thisSubBin < 2; thisSubBin++){
						/* Initialize. */
						var binMean = 0;
							binSTD = 0;
							thisDifferenceFromMean = 0;
						
						var totalBinIndex = thisTemplateCharacterBins[thisBin][thisSubBin].length;
						/* Add up all y coordinates. Then divide by total number to get the mean (as a percentage of height). */
						for (var subBinIndex = 0; subBinIndex < totalBinIndex; subBinIndex++) {
							binMean += thisTemplateCharacterBins[thisBin][thisSubBin][subBinIndex];
						}
						binMean = (binMean/totalBinIndex);	// addition / total = mean
						if(totalBinIndex==0){binMean=0;}
						
						/* Calculate the standard deviation [(each point - mean)^2]. */
						for (var subBinIndex = 0; subBinIndex < totalBinIndex; subBinIndex++) {
							var thisDifferenceFromMean = ( thisTemplateCharacterBins[thisBin][thisSubBin][subBinIndex] - binMean);
							binSTD += thisDifferenceFromMean*thisDifferenceFromMean;
						}
						binSTD = Math.sqrt(binSTD/(totalBinIndex-1))/boxRows;
						binMean = binMean/boxRows;
						if(totalBinIndex <= 1){binSTD=0;}
						
						/* Store the mean and standard deviation in the `letterTemplates`. */
						canvas1_zoning_properties[thisBin][thisSubBin] = [binMean, binSTD];
					}
				}
				
				/* Initialize the summations from this character. */
				canvas1_template_summations[k] = [];
				
				/* Cycle through each template of characters to determine if it is a match. */
				for (var k_character = 0; k_character < letterTemplates.length; k_character++){
					/* Determine the character's scale compared to the template. */
					height_template_scale = Math.round( (letterTemplates[k_character][1].length-1) / (boxRows-1) *1000)/1000;
					width_template_scale = Math.round( (letterTemplates[k_character][1][0].length-1) / (boxColumns-1) *1000)/1000;
					
					/* Initialize the summation for this template. */
					var thisTemplateCompare = [0, k_character, height_template_scale, width_template_scale, 0 /*row percentage*/, 0 /*column percentage*/, 0 /*M - percent of template matched*/, 0 /*N - percent of input character matched*/, [ [[],[]], [[],[]], [[],[]] ] /*cost of zonings (mean/STD)*/];
					
					/* Determine if will check EACH pixel against template OR CROP OUT the first row/column. */
					if (boxRows <= 5 || boxColumns <= 5) {
						var cropOutNumber = 0;
					} else {
						var cropOutNumber = 1;
					}
					
					/* Compare the zoning properties (mean/STD). */
					var total_mean_difference = 0,
						total_std_difference = 0;
					for (var thisBin = 0; thisBin < 3; thisBin++){
						for (var thisSubBin = 0; thisSubBin < 2; thisSubBin++){
							var mean_difference = ( canvas1_zoning_properties[thisBin][thisSubBin][0] - letterTemplates[k_character][5][thisBin][thisSubBin][0]);
							var std_difference = ( canvas1_zoning_properties[thisBin][thisSubBin][1] - letterTemplates[k_character][5][thisBin][thisSubBin][1]);
							
							//thisTemplateCompare[8][thisBin][thisSubBin][0] = mean_difference*mean_difference;
							//thisTemplateCompare[8][thisBin][thisSubBin][1] = std_difference*std_difference;
							
							total_mean_difference += mean_difference*mean_difference;
							total_std_difference += std_difference*std_difference;
						}
					}
					
					/* Cycle through each pixel in the character location to check it against a template. */
					for (data_row = cropOutNumber; data_row < boxRows-cropOutNumber; data_row++){
						adjustedRowIndex = Math.round( data_row * height_template_scale );
						//for (data_column = 1; data_column < boxColumns-1; data_column++){
						for (data_column = cropOutNumber; data_column < boxColumns-cropOutNumber; data_column++){
							/* **** THE COMPARISON ALGORITHM: **** */
							
							/* Compare each pixel of the inputted picture to each corresponding pixel in the template. */
							/* 
								For determining the mapping algorithm:
									template:		index: 0-4.				index+1: 1-5	length:5		scale: 0.2		
									input image:	index: 30-54 = 0-24.	index+1: 1-25	length:25		scale: 5		
									
									MAP algorithm: Y = (X-A)/(B-A) * (D-C) + C
									X = data_row
									Y = adjustedRowIndex
									A = boxTop
									B = boxBottom
									C = 0
									D = letterTemplates[k_character][1].length-1
									 -->	height_template_scale = Math.round( (letterTemplates[k_character][1].length-1) / (boxBottom - boxTop) *1000)/1000;
									 -->	adjustedRowIndex = Math.round( (data_row-boxTop) * height_template_scale );
							*/
							adjustedColumnIndex = Math.round( data_column * width_template_scale );
							
							/* Make sure the index is in the proper range. */
							if (adjustedRowIndex < 0) { adjustedRowIndex = 0; }
							else if (adjustedRowIndex >= letterTemplates[k_character][1].length) { adjustedRowIndex = letterTemplates[k_character][1].length-1; }
							
							if (adjustedColumnIndex < 0) { adjustedColumnIndex = 0; }
							else if (adjustedColumnIndex >= letterTemplates[k_character][1][0].length) { adjustedColumnIndex = letterTemplates[k_character][1][0].length-1; }
							
							/* **** THE HEART OF THE COMPARISON ALGORITHM: **** */
							
							/* Find certain values located in the `letterTemplates`. */
							templatePixelValue = letterTemplates[k_character][1][adjustedRowIndex][adjustedColumnIndex];
							templateRowPercentage = letterTemplates[k_character][2][adjustedRowIndex];
							templateColumnPercentage = letterTemplates[k_character][3][adjustedColumnIndex];
							
							/* Compare the `input image` to the `letterTemplates`. */
							thisTemplateCompare[0] += (canvas1_data[data_row+boxTop][data_column+boxLeft] - templatePixelValue)*(canvas1_data[data_row+boxTop][data_column+boxLeft] - templatePixelValue);
							thisTemplateCompare[4] += (canvas1_character_black_percent[k][0][data_row] - templateRowPercentage)*(canvas1_character_black_percent[k][0][data_row] - templateRowPercentage);
							thisTemplateCompare[5] += (canvas1_character_black_percent[k][1][data_column] - templateColumnPercentage)*(canvas1_character_black_percent[k][1][data_column] - templateColumnPercentage);
						}
					}
					thisTemplateCompare[6] = 1-Math.sqrt(thisTemplateCompare[0])/letterTemplates[k_character][4];
					thisTemplateCompare[7] = 1-Math.sqrt(thisTemplateCompare[0])/canvas1_character_black_percent[k][2];
					thisTemplateCompare[0] = Math.sqrt(thisTemplateCompare[0])/((boxRows-(cropOutNumber*2))*(boxColumns-(cropOutNumber*2)));
					thisTemplateCompare[4] = Math.sqrt(thisTemplateCompare[4])/(boxRows-(cropOutNumber*2));
					thisTemplateCompare[5] = Math.sqrt(thisTemplateCompare[5])/(boxColumns-(cropOutNumber*2));
					thisTemplateCompare[8] = total_mean_difference+total_std_difference;
					canvas1_template_summations[k][k_character] = thisTemplateCompare;
				}
			}
		};
		
		var findBestTemplateMatch = function (weight_1, weight_2, weight_3, weight_4, weight_5) {
			outputText = [];
			for (var k = 0; k < canvas1_character_location.length; k++) {
				/* Stores all the possible matches/summations for this particular character.
				   We will remove some of the values if conditions aren't met.
				   One index per template. each index contains [summation, index in character templates, height scale, width scale]. */
				var possibleBestSummations = canvas1_template_summations[k].slice(0);
				
				/* RULE OUT templates with summations/mismatches larger than a certain percentage of the input image.
				   Also, rule out templates with proportions (width-height ratio) very different than of the input image's. */
				for (var k_summation = 0; k_summation < possibleBestSummations.length; k_summation++) {
					/* Remove large summations: */
					if (possibleBestSummations[k_summation][0] > 0.40) {
						possibleBestSummations.splice(k_summation, 1);	// remove this template as a possibility for a match
						k_summation -= 1;
					}
					/* Remove non-proportional templates: */
					else if ( Math.abs(possibleBestSummations[k_summation][2]/possibleBestSummations[k_summation][3] -1) > 0.5) {
						possibleBestSummations.splice(k_summation, 1);	// remove this template as a possibility for a match
						k_summation -= 1;
					}
					/* Remove templates with very different black in row percentages. */
					else if ( Math.abs(possibleBestSummations[k_summation][4]) > 0.5 || Math.abs(possibleBestSummations[k_summation][5]) > 0.5) {
						possibleBestSummations.splice(k_summation, 1);	// remove this template as a possibility for a match
						k_summation -= 1;
					}
					/* Remove templates with a low M+N value */
					else if (0.6*possibleBestSummations[k_summation][6] + 0.4*possibleBestSummations[k_summation][7] < 0.70) {
						possibleBestSummations.splice(k_summation, 1);	// remove this template as a possibility for a match
						k_summation -= 1;
					}
				}
				
				/* Check if any possibilities left. */
				if (possibleBestSummations.length == 0) { outputText[k] = " "; continue; }
				
				/* Of those remaining, sort the possibilities for the lowest summations/black percentages. */
				var sortLowestSummations = possibleBestSummations.slice();
				sortLowestSummations.sort(function(a, b) {
					// a, b are the two sub-arrays of possibleBestSummations/sortLowestSummations to find the lower of the two.
					// By returning -1, `A` will come before `B`. By returning 1, `B` will come before `A`.
					// The lower summation comes first. In case of tie, `a` comes first.
					if (a[0] > b[0]) { return 1; }
					else { return -1; }
				});
				
				/* Find the lowest percentages of the rows/columns. (Only the lowest 6 summations) */
				
				/* Initialize. */
				var numberTemplateFinalists = 6;
				if (numberTemplateFinalists > sortLowestSummations.length) { numberTemplateFinalists = sortLowestSummations.length; }
				
				var sortLowestPercentageRow = sortLowestSummations.slice(0, numberTemplateFinalists);
				var sortLowestPercentageColumn = sortLowestSummations.slice(0, numberTemplateFinalists);
				var sortHighestWeightedMatch = sortLowestSummations.slice(0, numberTemplateFinalists);
				var sortLowestZoningMeanSTD = sortLowestSummations.slice(0, numberTemplateFinalists);
				
				/* SORT row/column percentages. */
				sortLowestPercentageRow.sort(function(a, b) {
					/* Find the one with the lower percentage. */
					if (a[4]/(boxRows-2) > b[4]/(boxRows-2)) { return 1; }
					else { return -1; }
				});
				sortLowestPercentageColumn.sort(function(a, b) {
					/* Find the one with the lower percentage. */
					if (a[5]/(boxColumns-2) > b[5]/(boxColumns-2)) { return 1; }
					else { return -1; }
				});
				sortHighestWeightedMatch.sort(function(a, b) {
					/* Find the one with the lower percentage. */
					if ( 0.6*a[6] + 0.4*a[7] < 0.6*b[6] + 0.4*b[7]) { return 1; }
					else { return -1; }
				});
				sortLowestZoningMeanSTD.sort(function(a, b) {
					/* Find the one with the lower percentage. */
					if ( a[8] > b[8] ) { return 1; }
					else { return -1; }
				});
				/* All sorting FINISHED. */
				
				/* Determine the best matched template by comparing the each possibility's index. */
				var sortAverageSortIndexes = sortLowestPercentageRow.slice(0);
				
				/* Match the index in `sortLowestPercentageRow` and FINAL COMPUTATION OF THE BEST MATCH. */
				for (var k_bestTemplate_1 = 0; k_bestTemplate_1 < numberTemplateFinalists; k_bestTemplate_1++) {
					var indexNumberInTemplates = sortAverageSortIndexes[k_bestTemplate_1][1];
					var indexNumber_PercentageRow = k_bestTemplate_1,
						indexNumber_PercentageColumn = 0,
						indexNumber_Summations = 0,
						indexNumber_WeightedMatch = 0;
					
					/* Find the index in `sortLowestSummations`. */
					for (var k_bestTemplate_2 = 0; k_bestTemplate_2 < numberTemplateFinalists; k_bestTemplate_2++) {
						if (sortLowestSummations[k_bestTemplate_2][1] == indexNumberInTemplates){
							indexNumber_Summations = k_bestTemplate_2;
							break;
						}
					}
					
					/* Find the index in `sortLowestPercentageColumn`. */
					for (var k_bestTemplate_3 = 0; k_bestTemplate_3 < numberTemplateFinalists; k_bestTemplate_3++) {
						if (sortLowestPercentageColumn[k_bestTemplate_3][1] == indexNumberInTemplates){
							indexNumber_PercentageColumn = k_bestTemplate_3;
							break;
						}
					}
					
					/* Find the index in `sortHighestWeightedMatch`. */
					for (var k_bestTemplate_4 = 0; k_bestTemplate_4 < numberTemplateFinalists; k_bestTemplate_4++) {
						if (sortHighestWeightedMatch[k_bestTemplate_4][1] == indexNumberInTemplates){
							indexNumber_WeightedMatch = k_bestTemplate_4;
							break;
						}
					}
					
					/* Find the index in `sortLowestZoningMeanSTD`. */
					for (var k_bestTemplate_5 = 0; k_bestTemplate_5 < numberTemplateFinalists; k_bestTemplate_5++) {
						if (sortLowestZoningMeanSTD[k_bestTemplate_5][1] == indexNumberInTemplates){
							indexNumber_ZoningMeanSTD = k_bestTemplate_5;
							break;
						}
					}
					
					/* Add all the numbers of indexes to find the average (weighted) index. */
					//sortAverageSortIndexes[k_bestTemplate_1].push( (3*indexNumber_Summations) + (1.2*indexNumber_PercentageRow) + (1*indexNumber_PercentageColumn) + (50*indexNumber_WeightedMatch) );
					sortAverageSortIndexes[k_bestTemplate_1].push( (weight_1*indexNumber_Summations) + (weight_2*indexNumber_PercentageRow) + (weight_3*indexNumber_PercentageColumn) + (weight_4*indexNumber_WeightedMatch) + (weight_5*indexNumber_ZoningMeanSTD) );
				}
				
				/* To find the final best match, sort for the average (weighted) of the various sortings. */
				sortAverageSortIndexes.sort(function(a, b) {
					/* Find the one with the lower percentage. */
					if ( a[a.length-1] > b[b.length-1] ) { return 1; }
					else { return -1; }
				});
				
				var bestMatchTemplate = sortAverageSortIndexes[0];
				
				/* OUTPUT the match. */
				if (typeof(bestMatchTemplate) == "object" && typeof(bestMatchTemplate[1]) == "number" && typeof(letterTemplates[bestMatchTemplate[1]]) == "object" && typeof(letterTemplates[bestMatchTemplate[1]][0]) == "string") {
					/* Add the recognized character with the lowest summation to the outputted text. */
					outputText[k] = letterTemplates[bestMatchTemplate[1]][0];
				}
				else {	// Otherwise, no match was found.
					//outputText[k] = "{-unrecognized-}";
					outputText[k] = " ";
				}
			}
		};
		
		var determineBestWeights = function () {
			var thisWeightK;
			
			if (select_image_name == "ocr_image3.png") {
				var correctOutputText = ["{", "3", "x", "5", "x", "x", "+", "+", "+", "2", "y", "+", "z", "_", "_", "3", "y", "+", "4", "z", "_", "_", "y", "_", "z", "_", "_", "1", "1", "2"];	// ""
				var sensitiveOutputText = [1,2,3,4,5,6,7,8,9,10,11,15,16,17,18,22,27,28,29];
			}
			else if (select_image_name == "ocr_image5.jpg") {
				var correctOutputText = ["3", "x", "_", "2", "_", "_", "7"];
				var sensitiveOutputText = [0,1,2,3,4,5,6];
			}
			else if (select_image_name == "ocr_image8.png") {
				var correctOutputText = ["2","(","5","x","+","1",")","_","3","(","2","x","_","2",")","_","_","2","8"];	// "2 ( 5 x + 1 ) - 3 ( 2 x - 2 ) _ _ 2 8"
				var sensitiveOutputText = [0,2,3,4,8,10,11,13,17,18];
			}
			else if (select_image_name == "ocr_image11.jpg") {
				var correctOutputText = ["x","+","6","_","_","1","0","(","x","+","6",")","_","_","(","1","0",")","_","6","x","_","_","4"];	// "x+6__10(x+6)__(10)_6x__4"
				var sensitiveOutputText = [0,1,2,6,7,8,9,10,11,14,16,17,19,20,23];
			}
			else if (select_image_name == "ocr_image12.jpg") {
				var correctOutputText = ["2","x","_","6","_","_","4"];	// "2x_6__4"
				var sensitiveOutputText = [0,1,3,5,6];
			}
			else {
				console.log("Error. Image not found.");
				return;
			}
			console.log("Started determining best weights.");
			
			/* Initialize the weight possibilities. */
			var weightPossibilites = [];
			for (var weight1 = 0; weight1 <= 7; weight1++){
				for (var weight2 = 0; weight2 <= 7; weight2++){
					for (var weight3 = 0; weight3 <= 7; weight3++){
						for (var weight4 = 0; weight4 <= 7; weight4++){
							for (var weight5 = 0; weight5 <= 7; weight5++){
								if ( weight1%2 == 0 && weight2%2 == 0 && weight3%2 == 0 && weight4%2 == 0 && weight5%2 == 0) {continue;}
								weightPossibilites.push([weight1, weight2, weight3, weight4, weight5]);
							}
						}
					}
				}
			}
			weightPossibilites.splice(0,1);
			//console.info(weightPossibilites.length + " possibilities.");

			/* Cycle through each possibility. */
			for (var weightK = 0; weightK < weightPossibilites.length; weightK++) {
				thisWeightK = weightPossibilites[weightK];
				findBestTemplateMatch(thisWeightK[0], thisWeightK[1], thisWeightK[2], thisWeightK[3], thisWeightK[4]);
				
				if (correctOutputText.length !== outputText.length) {
					console.error("Error. Length mismatch.");
					return;
				}
				
				/* Check each output text/character with the correct one. If mismatch, remove that possibility. */
				for (var sensitiveTextK = 0; sensitiveTextK < sensitiveOutputText.length; sensitiveTextK++) {
					var sensitiveOutputNumber = sensitiveOutputText[sensitiveTextK];
					if ( outputText[sensitiveOutputNumber] !== correctOutputText[sensitiveOutputNumber] ) {
						weightPossibilites.splice(weightK,1);
						weightK-=1;
						break;
					}
				}
			}
			console.info(weightPossibilites.length + " possibilities left.");
			return;
		};
		
		/* ~~~~~~~~~~~ SHAPE CONTEXT: ~~~~~~~~~~~ **/
		
		/* Convert image to grayscale: */
		var convertToGrayscale = function () {
			for (var pH=0; pH < imageHeight; pH++){
				/* Load the image data and store. This is the only time we need to do this for the image.*/
				pixelImageData = context.getImageData(0, pH, imageWidth, 1).data;
				canvas1_graystyle[pH] = [];
				canvas1_rgb[pH] = [];
				
				for (var pW=0; pW < imageWidth; pW++){	// width
					var pW_array = pW*4;	// stores that pixel's location within array of rgba for many pixels (each 'r' 'g' 'b' and 'a' is a separate value)
					pixel_D = [];			// stores that pixel's rgb data, which will be processed, then stored.
					pixel_D[0] = pixelImageData[pW_array];		// r
					pixel_D[1] = pixelImageData[pW_array+1];	// g
					pixel_D[2] = pixelImageData[pW_array+2];	// b
					pixel_D[3] = pixelImageData[pW_array+3];	// a
					
					canvas1_rgb[pH][pW] = [pixel_D[0], pixel_D[1], pixel_D[2]];	// stores true rgb values for each pixel
					canvas1_graystyle[pH][pW] = Math.round( (0.3*pixel_D[0] + 0.59*pixel_D[1] + 0.11*pixel_D[2]) );
				}
			}
		};
		
		/* Applies a Gaussian blur to the image. */
		var gaussianBlur = function(sigma, size) {
			if (sigma <= 0) { sigma = 1; }
			if (size < 1) {size = 1;}
			
			canvas1_blurredGraystyle = [];
			var kernel = generateKernel(sigma, size);
			
			/* For every point in image, apply Gaussian filter: */
			for (var a = 0; a < imageHeight; a++) {
				canvas1_blurredGraystyle[a] = [];
				for (var b = 0; b < imageWidth; b++) {
					/* Initialize. */
					var resultGray = 0;
					
					/* Cycle through all surrounding pixels (xy location) to average into this pixel. */
					for (var i = 0, x = (a - (size-1)/2); i < size; i++, x++) {
						for (var j = 0, y = (b - (size-1)/2); j < size; j++, y++) {
							if (x < 0) { x = 0; }
							else if (x >= imageHeight) { x = imageHeight-1; }
							
							if (y < 0) { y = 0; }
							else if (y >= imageWidth){ y = imageWidth-1; }
							
							resultGray += canvas1_graystyle[x][y] * kernel[i][j];
						}
					}
					
					/* Adjust the graystyle to that "average". */
					canvas1_blurredGraystyle[a][b] = Math.round(resultGray);
				}
			}
			
			function generateKernel(sigma, size) {
				/* Initialize. */
				var matrix = [];
				var E = 2.71828;		//Euler's number rounded off to 3 places
				
				/* Create the kernel matrix with the formula found here: http://upload.wikimedia.org/math/e/9/5/e95ce25641ab5e80f4b9e03453544385.png */
				for (var y = -(size - 1)/2, i = 0; i < size; y++, i++) {
					matrix[i] = [];
					for (var x = -(size - 1)/2, j = 0; j < size; x++, j++) {
						//create matrix round to 3 decimal places
						var twoSigmaSquared = 2*sigma*sigma;
						matrix[i][j] = 1/(3.141592653589793 * twoSigmaSquared) * Math.pow( E, -( x*x + y*y )/(twoSigmaSquared) );
					}
				}
				
				/* Normalize the matrix to make its sum 1. */
				var normalize = 1/sumMatrix(matrix);
				for (var k = 0; k < matrix.length; k++) {
					for (var l = 0; l < matrix[k].length; l++) {
						matrix[k][l] = Math.round(normalize * matrix[k][l] * 1000)/1000;
					}
				}
				
				/* Return the kernel. */
				return matrix;
			}
			
			/* Receives an array, and returns the sum of all its values. */
			function sumMatrix (arr) {
				var result = 0;
				for (var i = 0; i < arr.length; i++) {
					if (/^\s*function Array/.test(String(arr[i].constructor))) {
						result += sumMatrix(arr[i]);
					} else {
						result += arr[i];
					}
				}
				return result;
			};
		};
		
		/* Find intensity gradient of image. */
		var sobelEdgeDetection = function () {
			var dirMap = [];
			var gradMap = [];
			
			/* Perform vertical convolution. */
			var xfilter = [ [-1, 0, 1],
							[-2, 0, 2],
							[-1, 0, 1] ];
			/* Perform horizontal convolution. */
			var yfilter = [ [1, 2, 1],
							[0, 0, 0],
							[-1, -2, -1] ];
			
			/* For every point in image, apply Sobel Edge Detection: */
			for (var a = 0; a < imageHeight; a++) {
				dirMap[a] = [],
				gradMap[a] = [];
				for (var b = 0; b < imageWidth; b++) {
					/* Initialize. */
					var edgeX = 0;
					var edgeY = 0;
					
					/* Do not apply Sobel to edges of the image. */
					if (a <= 0 || a >= imageHeight-1 || b <= 0 || b >= imageWidth-1 ) {
						//dirMap[a][b] = 0;
						//gradMap[a][b] = 0;
						//continue;
					}
					else {
						/* Cycle through all surrounding pixels (xy location) to average into this pixel. */
						for (var i = 0; i < 3; i++) {
							for (var j = 0; j < 3; j++) {
								var x = a - 1 + i;
								var y = b - 1 + j;
								edgeX += canvas1_blurredGraystyle[x][y] * xfilter[i][j];
								edgeY += canvas1_blurredGraystyle[x][y] * yfilter[i][j];
							}
						}
					}
					
					var dir = roundDir(Math.atan2(edgeY, edgeX) * 57.295779513);	// 180/Math.PI = 57.295779513
					dirMap[a][b] = dir;
					
					var grad = Math.round(Math.sqrt(edgeX*edgeX + edgeY*edgeY));
					gradMap[a][b] = grad;
				}
			}
			
			/* Returns true if a pixel lies on the border of an image. */
			//function checkCornerOrBorder(i, width, height) {	// TODO - does this work??
				// a 
				// i = (width * (b) + a)
				// 			a / width < 1 - b									b <= 1 && 
				// 		||  (width * b + a) % (width) === 0
				//		||  (width * b + a) % (width) === width - 1
				//		||  width*(b + 1) + a > width*height;
				// return i - (width * 4) < 0 || i % (width * 4) === 0 || i % (width * 4) === (width * 4) - 4  || i + (width * 4) > width * height * 4;
			//	return i - width < 0 || i % width === 0 || i % width === width - 1  || i + width > width * height;
			//}
			
			/* Rounds degrees to 4 possible orientations: horizontal, vertical, and 2 diagonals. */
			function roundDir(deg) {
				if (deg < -180 || deg > 180) {
					console.info("Warning in roundDir! deg = " + deg);
				}
				
				/* Range of deg is between -180 and 180. Add 180 to make it positive. */
				if (deg < 0) { deg += 180; }
				
				var roundVal;
				if ((deg >= 0 && deg <= 22.5) || (deg > 157.5 && deg <= 180)) {
					roundVal = 0;
				} else if (deg > 22.5 && deg <= 67.5) {
					roundVal = 45;
				} else if (deg > 67.5 && deg <= 112.5) {
					roundVal = 90;
				} else if (deg > 112.5 && deg <= 157.5) {
					roundVal = 135;
				}
				else {	// error!
					console.log("Error in roundDir! deg = " + deg);
					roundVal = 0;
				}
				return roundVal;
			}
			
			canvas1_sobelEdgeDirMap = copy_array(dirMap);
			canvas1_sobelEdgeGradMap = copy_array(gradMap);
			canvas1_sobelEdgeGradient = copy_array(gradMap);
		};
		
		/* Thin the edges. */
		var nonMaximumSuppressFilter = function() {
			/* Initialize. */
			canvas1_nonMaxSuppress = copy_array(canvas1_sobelEdgeGradient);
			
			/* For every point in image, apply non-max-suppression: */
			for (var a = 1; a < imageHeight-1; a++) {
				for (var b = 1; b < imageWidth-1; b++) {
					/* Initialize. */
					var pixNeighbors = getNeighbors( canvas1_sobelEdgeDirMap[a][b] );
					
					/* Pixel neighbors to compare. */
					var pix1 = canvas1_sobelEdgeGradMap[a + pixNeighbors[0].x - 1][b + pixNeighbors[0].y - 1];
					var pix2 = canvas1_sobelEdgeGradMap[a + pixNeighbors[1].x - 1][b + pixNeighbors[1].y - 1];
					var pixCurrent = canvas1_sobelEdgeGradMap[a][b];
					
					/* Suppress certain pixels if neighbors have a higher gradient. */
					if (pix1 > pixCurrent || pix2 > pixCurrent) {
						canvas1_nonMaxSuppress[a][b] = 0;
					} else if (pix2 === pixCurrent && pix1 < pixCurrent) {
						canvas1_nonMaxSuppress[a][b] = 0;
					}
				}
			}
			
			function getNeighbors(dir) {
				var degrees = {0 : [{x:1, y:2}, {x:1, y:0}], 45 : [{x: 0, y: 2}, {x: 2, y: 0}], 90 : [{x: 0, y: 1}, {x: 2, y: 1}], 135 : [{x: 0, y: 0}, {x: 2, y: 2}]};
				return degrees[dir];
			}
		};
		
		/* Mark strong and weak edges, discard others as false edges; only keep weak edges that are connected to strong edges. */
		var hysteresis = function(t1, t2) {	
			var realEdges = [];		//where real edges will be stored with the 1st pass
			
			/* Determine thresholds for hysteresis. */
			if (typeof(t1) == "undefined" || typeof(t2) == "undefined") {
				var t1 = 170;			//high threshold value
				var t2 = 100;			//low threshold value
			}
			
			/* First pass. */
			for (var a = 0; a < imageHeight; a++) { realEdges[a] = []; }
			for (var a = 0; a < imageHeight; a++) {
				for (var b = 0; b < imageWidth; b++) {
					if (canvas1_nonMaxSuppress[a][b] > t1 && realEdges[a][b] === undefined) {	//accept as a definite edge
						var group = traverseEdge([a, b], t2, []);
						for (var i = 0; i < group.length; i++) {
							realEdges[ group[i][0] ][ group[i][1] ] = true;
						}
						
						/* The calculated group of pixels forms the edge of a single character (or noise). */
						canvas1_characterEdges.push( copy_array(group) );
					}
				}
			}
			
			/* Second pass. */
			for (var a = 0; a < imageHeight; a++) {
				canvas1_hysteresis[a] = [];
				for (var b = 0; b < imageWidth; b++) {
					if (realEdges[a][b] === undefined) {
						canvas1_hysteresis[a][b] = 0;
					} else {
						canvas1_hysteresis[a][b] = 255;
					}
				}
			}
			
			/* Traverses the current pixel until a length has been reached. */
			function traverseEdge (current_xy, threshold, traversed) {	
				/* Initialize the group from the current pixel's perspective. */
				var group = [current_xy];
				
				/* Pass the traversed group to the `getNeighborEdges` so that it will not include those anymore. */
				var neighbors = getNeighborEdges(current_xy, threshold, traversed);
				
				for (var i = 0; i < neighbors.length; i++) {
					/* Recursively get the other edges connected. */
					group = group.concat( traverseEdge(neighbors[i], threshold, traversed.concat(group)) );
				}
				
				/* If the pixel group is not above max length, it will return the pixels included in that small pixel group. */
				return group;
			}
			
			function getNeighborEdges (i, threshold, includedEdges) {
				var neighbors = [];
				var a = i[0];
				var b = i[1];
				
				var directions = [
					[a,   b+1],	//e
					[a-1, b+1],	//ne
					[a-1, b],	//n
					[a-1, b-1],	//nw
					[a,   b-1],	//w
					[a+1, b-1],	//sw
					[a+1, b],	//s
					[a+1, b+1]	//se
				];
				
				for (var j = 0; j < 8; j++) {
					/* If the edge is above the threshold and has not already been processed: add it to `neighbors`. */
					//if ( canvas1_nonMaxSuppress[ directions[j][0] ][ directions[j][1] ] >= threshold && (includedEdges === undefined || includedEdges.indexOf( directions[j] ) === -1)	) {
					if ( canvas1_nonMaxSuppress[ directions[j][0] ][ directions[j][1] ] >= threshold && indexOfCustom(includedEdges, directions[j]) == false ) {
						neighbors.push(directions[j]);
					}
				}
				return neighbors;
			}
			
			function indexOfCustom (parentArray, searchElement) {
				if (parentArray == undefined) { return false; }
				for ( var m = 0; m < parentArray.length; m++ ) {
					if ( parentArray[m][0] == searchElement[0] && parentArray[m][1] == searchElement[1] ) {
						return true;
					}
				}
				return false;
			}
		};
		
		/* Combine characters which have an inner and outer edge: */
		var combineCharacterEdges = function() {
			/* Compute each characters' borders: */
			var characterBorders = [];
			for (k = 0; k < canvas1_characterEdges.length; k++){
				/* Initialize: */
				var y = canvas1_characterEdges[k][0][0];
				var x = canvas1_characterEdges[k][0][1];
				characterBorders[k] = {};
				characterBorders[k].top = [y,x];
				characterBorders[k].bottom = [y,x];
				characterBorders[k].left = [y,x];
				characterBorders[k].right = [y,x];
				
				/* For each point, check if outside current boundaries: */
				var thisCharacterSet = canvas1_characterEdges[k];
				var thisCharacterLen = canvas1_characterEdges[k].length;
				if (thisCharacterLen < 5) { canvas1_characterEdges.splice(k, 1); k-=1; continue; }
				for (var p=1; p < thisCharacterLen; p++) {
					y = thisCharacterSet[p][0];
					x = thisCharacterSet[p][1];
					
					if (y < characterBorders[k].top[0])		{ characterBorders[k].top = [y, x]; }
					if (y > characterBorders[k].bottom[0])	{ characterBorders[k].bottom = [y, x]; }
					if (x < characterBorders[k].left[1])	{ characterBorders[k].left = [y, x]; }
					if (x > characterBorders[k].right[1])	{ characterBorders[k].right = [y, x]; }
				}
			}
			
			/* Check this character against all others (to see if they are edges to the same character): */
			for (var k = 0; k < canvas1_characterEdges.length; k++){
				for (var c = 0; c < canvas1_characterEdges.length; c++){
					if (k == c) { continue; }
					
					/* Initialize: */
					var is_outside_top = false,
						is_outside_bottom = false,
						is_outside_left = false,
						is_outside_right = false;
					
					/* Cycle through each point in the second image: */
					var thisCharacterSet = canvas1_characterEdges[c];
					var thisCharacterLen = canvas1_characterEdges[c].length;
					for (p = 0; p < thisCharacterLen; p++){
						/* Top: */
						if (thisCharacterSet[p][1] == characterBorders[k].top[1]){
							if (thisCharacterSet[p][0] > characterBorders[k].top[0]){
								is_outside_top = true;
							}
						}
						/* Bottom: */
						if (thisCharacterSet[p][1] == characterBorders[k].bottom[1]){
							if (thisCharacterSet[p][0] < characterBorders[k].bottom[0]){
								is_outside_bottom = true;
							}
						}
						/* Left: */
						if (thisCharacterSet[p][0] == characterBorders[k].left[0]){
							if (thisCharacterSet[p][1] > characterBorders[k].left[1]){
								is_outside_left = true;
							}
						}
						/* Right: */
						if (thisCharacterSet[p][0] == characterBorders[k].right[0]){
							if (thisCharacterSet[p][1] < characterBorders[k].right[1]){
								is_outside_right = true;
							}
						}
					}
					
					/* If character is entirely outside the second, combine the two: */
					if (is_outside_top && is_outside_bottom && is_outside_left && is_outside_right) {
						canvas1_characterEdges[k] = canvas1_characterEdges[k].concat( canvas1_characterEdges[c] );
						canvas1_characterEdges.splice(c, 1);
						characterBorders.splice(c, 1);
						if (k > c) { k -= 1; }
						c -= 1;
					}
				}
			}
			
			/* Reorder the characters from left to right, not from top: */
			for (var k = 0; k < characterBorders.length; k++){ characterBorders[k].canny_index = k; }
			characterBorders.sort(function(a, b) {
				// -1 : 'A' before 'B'
				//  1 : 'B' before 'A'
				if ( a.left[1] > b.left[1] ) { return 1; }
				else { return -1; }
			});
			characterEdges_cpy = [];
			for (var k = 0; k < characterBorders.length; k++){
				//characterEdges_cpy[ characterBorders[k].canny_index ] = canvas1_characterEdges[k];
				characterEdges_cpy[k] = canvas1_characterEdges[ characterBorders[k].canny_index ];
			}
			canvas1_characterEdges = characterEdges_cpy;
			canvas1_characterBorders = characterBorders;
		};
		
		/* This is the master function for Canny Edge Detection: */
		var cannyEdgeDetection = function () {
			convertToGrayscale();
			gaussianBlur(1.5, 3);
			sobelEdgeDetection();
			nonMaximumSuppressFilter();
			hysteresis(170, 100);
			combineCharacterEdges();
				display_graystyle_canvas1(canvas1_hysteresis);	// canvas1_characterEdges
		};
		
		/* Limit the number of edges per character with which to compute Shape Context: */
		var limitNumberEdges = function () {
			var numPoints = numEdgeHistPoints;
			
			for (var c = 0; c < canvas1_characterEdges.length; c++) {
				canvas1_limitedNumberEdges[c] = [];
				var numOriginal = canvas1_characterEdges[c].length;
				var numRatio = numOriginal/numPoints;
				
				var numExact = 0;
				var numRounded = 0;
				
				while (true) {
					y = canvas1_characterEdges[c][numRounded][0];
					x = canvas1_characterEdges[c][numRounded][1];
					canvas1_limitedNumberEdges[c].push( [y, x] );
					
					numExact += numRatio;
					numRounded = Math.round(numExact);
					if (numRounded > numOriginal-1) {
						/* Check if has reached the number of points to limit. Otherwise, add on the last point: */
						if (canvas1_limitedNumberEdges[c].length < numPoints) {
							numRounded = numOriginal-1;
						} else { break; }
					}
				}
			}
			// example output: display_coor_list_canvas1( canvas1_limitedNumberEdges[1] );
		};
		
		/* ~~~~~~~~~~~ MAIN RECOGNITION STAGE: ~~~~~~~~~~~ **/
		
		/* Create the shape-context edge descriptor for a list of edges: */
		var computeShapeContext = function ( thisEdgeList ) {
			var polarLogs = [],
				histogram = [],
				meanDist = 0,
				numEdges = thisEdgeList.length;
			
			/* Compute shape context for each point of character edge: */
			for (var p = 0; p < numEdges; p++) {
				/* Initialize histogram: */
				histogram[p] = [],
				polarLogs[p] = [];
					
				for (var k=0; k<12; k++) {
					polarLogs[p][k] = [];
					histogram[p][k] = [];
					for (var j=0; j<5; j++) { histogram[p][k][j] = 0; }
				}
				
				/* For each other point, calculate Euclidean distance and angle: */
				var centerY = thisEdgeList[p][0],
					centerX = thisEdgeList[p][1];
				for (var e = 0; e < numEdges; e++) {
					/* Initialize: */
					var thisY = thisEdgeList[e][0];
					var thisX = thisEdgeList[e][1];
					var dy = thisY - centerY;
					var dx = thisX - centerX;
					if (dx == 0 && dy == 0) { continue; }
					
					/* Compute distance: */
					var distance = Math.sqrt(dx*dx + dy*dy);
					
					/* Compute angle from center point `p`: */
					var angle = Math.asin(dy/distance) * 180/Math.PI;		// much faster than if used `atan2`.
					if (angle < 0) { angle += 360; }
					var binAngle = Math.floor(angle/30);
					
					polarLogs[p][binAngle].push(distance);
					meanDist += distance;
				}
			}
			
			/* Compute the mean for this character: */
			meanDist = meanDist / (numEdges * (numEdges-1));
			
			/* Normalize each Shape Context by the above mean: */
			for (var p = 0; p < numEdges; p++) {
				/* For each point in each angle bin, classify into log bin: */
				for (var b=0; b<12; b++) {
					for (var e = 0; e < polarLogs[p][b].length; e++) {
						/* Normalize by mean distance: */
						var normDist = polarLogs[p][b][e]/meanDist;		// usually ranges between 0.0 and 2.0
						
						/* Determine logarithmic bin: */
							 if (normDist < 0.125) { logBin = 0; }
						else if (normDist < 0.25) { logBin = 1; }
						else if (normDist < 0.5) { logBin = 2; }
						else if (normDist < 1)	{ logBin = 3; }
						else if (normDist < 2) { logBin = 4; }
						else				  {  continue;  }
						
						/* Add edge to histogram: */
						histogram[p][b][logBin] += 1;
					}
				}
			}
			
			/* Return histogram list: */
			return histogram;
		};
		
		/* Compute the shape-context matching cost (Hungarian): */
		var findHistogramPairs = function (charHistList, tempHistList) {
			var numEdges = numEdgeHistPoints;
			
			/* Initialize cost matrix: */
			var shapeCostMatrix = [];
			for (var n = 0; n < numEdges; n++) {
				shapeCostMatrix[n] = [];
				for (var m = 0; m < numEdges; m++) {
					shapeCostMatrix[n][m] = 0;
				}
			}
			
			/* For each edge in input character: */
			for (var ce = 0; ce < numEdges; ce++) {
				/* For each edge in template character: */
				for (var te = 0; te < numEdges; te++) {
					/* Calculate and store the matching cost between histograms: */
					shapeCostMatrix[ce][te] = ChiSquaredTestStatistic( charHistList[ce], tempHistList[te] );
				}
			}
		
			/* Find the best matching edge using the Hungarian method: */
			return hungarian(shapeCostMatrix);
			
			edgeAssignments = hungarian(shapeCostMatrix);
			/* Compute the cost of matching: */
			var distanceCost = 0;
			for (i = 0; i < edgeAssignments.length; i++) {
				distanceCost += shapeCostMatrix[i][ edgeAssignments[i][1] ];
			}
			
			/* Return the corresponding pairs and the matching cost: */
			// return [edgeAssignments, distanceCost];
		};
		
		/* Apply transformations: */
		var applyTransformations = function (thisEdgeList, targetEdgeList, edgeAssignments) {
			/* Affine model: */
			var scale = [1, 1],
				offset = [0, 0],
				totalPoints = thisEdgeList.length;
			
			/* Calculate offset vector: */
			for (var i = 0; i < totalPoints; i++) {
				var j = edgeAssignments[i][1];
				offset[0] += (thisEdgeList[i][0] - targetEdgeList[j][0]);
				offset[1] += (thisEdgeList[i][1] - targetEdgeList[j][1]);
			}
			offset[0] /= totalPoints;
			offset[1] /= totalPoints;
			
			/* Calculate scale matrix: */
			pMatrix = [];
			qMatrix = [];
			for (var i = 0; i < totalPoints; i++) {
				pMatrix.push([ 1, thisEdgeList[i][0], thisEdgeList[i][1] ]);
				qMatrix.push([ 1, targetEdgeList[i][0], targetEdgeList[i][1] ]);
			}
			//scale = multiplyMatrix(pMatrix, qMatrix);
			scale = [1, 1];
			
			/* Apply the transformation: */
			for (var i = 0; i < totalPoints; i++) {
				thisEdgeList[i][0] = Math.round( thisEdgeList[i][0] * scale[0] + 0 );
				thisEdgeList[i][1] = Math.round( thisEdgeList[i][1] * scale[1] + 0 );
			}
			
			/* Return the new set of coordinates: */
			return thisEdgeList;
		};
		
		/* Determine matching cost between histograms using the `chi-squared-test-statistic`: */
		function ChiSquaredTestStatistic (testHist, tempHist) {
			sumCSTS = 0;
			for (var ka = 0; ka < 12; ka++){
				for (var kd = 0; kd < 5; kd++){
					var testBin = testHist[ka][kd];
					var tempBin = tempHist[ka][kd];
					if (testBin == tempBin) { continue; }
					
					sumCSTS += (testBin-tempBin)*(testBin-tempBin)/(testBin+tempBin);
				}
			}
			return Math.round(sumCSTS/2);
		}
		
		/* Matrix multiplication helper function: */
		function multiplyMatrix(a, b) {
			var aNumRows = a.length, aNumCols = a[0].length,
				bNumRows = b.length, bNumCols = b[0].length,
				m = new Array(aNumRows);  // initialize array of rows
			for (var r = 0; r < aNumRows; ++r) {
				m[r] = new Array(bNumCols); // initialize the current row
				for (var c = 0; c < bNumCols; ++c) {
					m[r][c] = 0;             // initialize the current cell
					for (var i = 0; i < aNumCols; ++i) {
						m[r][c] += a[r][i] * b[i][c];
					}
				}
			}
			return m;
		}
		
		/* ~~~~~~~~~~~ MAIN PROGRAM STAGES : ~~~~~~~~~~~ **/
		
		/* Preprocessing stage */
		var preprocessing = function () {
			init_canvas();
			init_canvas1_variables();
			cannyEdgeDetection();
			limitNumberEdges();
		};
		
		/* Main recognition stage using shape context: */
		var recognitionStage = function () {
			var totalCycles = 2;
			outputText = [];
			
			/* Run algorithm for each input character: */
			for (var c = 0; c < canvas1_characterEdges.length; c++) {
				console.time("Recognition time");
				
				/* Steps:
				 * generate initial shape context
				 * for each template:
				 *   compute cost matrix
				 *     calculate shape-context matching cost
				 *     [calculate additional appearance cost]
				 *   find best matching which minimizes total cost
				 *   apply transformations to align edge pairs
				 *   regenerate shape context
				 *   repeat previous steps 2-3 times
				 *   compute final total shape-context distance
				 * select template with lowest cost
				 * output to log
				 */
				
				
				/* For each character in template: */
				var matchingCost = [];
				for (var t = 0; t < letterTemplates.length; t++){
					/* Initialize edge lists: */
					var edgeList = copy_array( canvas1_limitedNumberEdges[c] );
					var targetEdgeList = letterTemplatesEdges[t];
					// var targetEdgeList = copy_array( edgeList );
					var targetHistList = letterTemplates[t];
					
					/* Find correspondences, run transformations, and re-compute: */
					for (var transformCycle = 1; transformCycle <= totalCycles; transformCycle++) {
						var histogramList = computeShapeContext( edgeList );
						var edgeAssignments = findHistogramPairs(histogramList, targetHistList);
						if (transformCycle == totalCycles){continue;}
						var edgeList = applyTransformations(edgeList, targetEdgeList, edgeAssignments);
					}
					
					/* Compute the final shape-context distance: */
					var distanceCost = 0;
					for (i = 0; i < edgeAssignments.length; i++) {
						distanceCost += ChiSquaredTestStatistic( histogramList[i], targetHistList[ edgeAssignments[i][1] ] );
						//distanceCost += shapeCostMatrix[i][ edgeAssignments[i][1] ];
					}
					
					var transformCost = 0;
					var totalCost = distanceCost + transformCost;
					matchingCost.push([t, totalCost, letterTemplatesText[t]]);
				}
				
				/* Sort the templates by best match (lowest cost): */
				matchingCost.sort(function(a,b){
					if ( b[1] < a[1] ) { return 1; }
					return -1;
				});
				
				/* Find the best match to template: */
				var bestMatchIndex = matchingCost[0][0];
				outputText.push( letterTemplatesText[bestMatchIndex] );
				
				console.log( outputText[outputText.length-1] );
				console.timeEnd("Recognition time");
			}
			
		};
		
		var postProcessing = function () {
			/* Output the text. */
			output_text_span.innerHTML = "The recognized text is: ";
			for (var k = 0; k < outputText.length; k++) { output_text_span.innerHTML += outputText[k]; }
			
			console.log("OCR complete. Outputted text: " + output_text_span.innerHTML );
		};
		
		var generateHistTemplates = function () {
			preprocessing();
			console.log("This function is not currently available."); return;
			computeShapeContext();
			console.clear();
			console.log( 'letterTemplates = ' + JSON.stringify(canvas1_characterHistograms) + ';\nletterTemplatesText = "";\n\n');
		};
		
		/* ~~~~~~~~~~~ DRAG AND DROP LISTENERS: ~~~~~~~~~~~ **/
		
		document.addEventListener("dragenter", dragenter, false);
		document.addEventListener("dragover", dragover, false);
		document.addEventListener("dragleave", dragleave, false);
		document.addEventListener("dragend", dragend, false);
		document.addEventListener("drop", drop, false);
		
		function dragenter(e) {
			e.stopPropagation();
			e.preventDefault();
		}
		function dragover(e) {
			e.stopPropagation();
			e.preventDefault();
			document.getElementById("drop-overlay").style.display = "block";
		}
		function dragleave(e) {
			document.getElementById("drop-overlay").style.display = "none";
		}
		function dragend(e) {
			document.getElementById("drop-overlay").style.display = "none";
		}
		function drop(e) {
			e.stopPropagation();
			e.preventDefault();
			
			document.getElementById("drop-overlay").style.display = "none";
			
			var dt = e.dataTransfer;
			var files = dt.files;
			var items = dt.items;
			
			if (files.length > 0) {
				handleFiles(files);
			}
			else if (items.length > 0) {
				e.dataTransfer.items[0].getAsString(function(url){
					//alert(url);
					
					/*
					var reader = new FileReader();
					reader.onload = function (){
						image.src = this.result;
						select_image_name = "a_web_image1";
						image.onload = function () {
							//run_ocr_on_image();
						}
					};
					reader.readAsDataURL( dataUriToBlob(url) );
					*/
					
					
					select_image_name = "a_web_image1";
					// image.crossOrigin = "Anonymous";
					image.src = url;
					image.onload = function () {
						//run_ocr_on_image();
					}
				});
			}
		}
		function handleFiles(files) {
			if (files.length > 0) {
				window.thisFile = files[0];
				var imageType = /^image\//;
				
				if (!imageType.test(window.thisFile.type)) { return; }
				
				var reader = new FileReader();
				reader.onload = function (){
					image.src = this.result;
					select_image_name = window.thisFile.name;
					image.onload = function () {
						init_canvas();
						//run_ocr_on_image();
					}
				};
				reader.readAsDataURL(window.thisFile);
			}
		}
		
		/* Master function: */
		function run_ocr_on_image() {
			//ocr_master(150,50,0,500,100,1);
			//debugMode = gridline_checkbox.checked;
			//binarizeWithOtsu(10);
			//colorCompareFilter(500);
			//removeBorder();
			//fixSinglePixelNoise(2);
			//fixLargePixelNoise(100);
			//separateCharacters();
			//orderCharactersByStructure();
			//display_canvas1();
			//generateTemplates();
			//templateMatching();
			//findBestTemplateMatch(3, 1.2, 1, 10, 30);
			//console.log(outputText);
			
			preprocessing();
			recognitionStage();
			postProcessing();
		}
	</script>
	<script defer src="letterTemplates.js"></script>
</body></html>