%% Canny Edge Detection
function out = CannyEdgeDetection (colorIMG)
    DEFAULT = OcrDefaults;
    out.grayIMG = convertToGrayscale(colorIMG);
    out.blurIMG = gaussianBlur(out.grayIMG, DEFAULT.gaussianSigma, DEFAULT.gaussianSize);
    out.sobelIMG = sobelEdgeDetection(out.blurIMG);
    out.edgesIMG = hysteresis(out.sobelIMG, DEFAULT.hysteresis);
    out.edgesLIST = blobDetection(out.edgesIMG);
end

%% Convert To Grayscale
function out = convertToGrayscale(IMG)
    if (length(size(IMG)) ~= 3), out = double(IMG); return; end
    out = double(0.299*IMG(:,:,1) + 0.587*IMG(:,:,2) + 0.114*IMG(:,:,3));
end

%% Gaussian Blur
function out = gaussianBlur(grayIMG, sigma, radius)
    % Create the kernel matrix with the formula found here:
    % http://upload.wikimedia.org/math/e/9/5/e95ce25641ab5e80f4b9e03453544385.png
    [X,Y] = meshgrid(-radius:radius,-radius:radius);
    matrix = 1/(2*pi*sigma^2) * exp( -(X.^2 + Y.^2)/(2*sigma^2) );
    kernel = matrix/norm(matrix,'fro');

    % Apply filter for each pixel
    out = conv2(grayIMG, kernel, 'same');
end

%% Sobel Edge Detection and Non Maximum Suppress Filter
function out = sobelEdgeDetection (blurIMG)
    [h, w] = size(blurIMG);
    nonMaxFilter = zeros(h, w);
    xfilter = [ -1 0 1; -2 0 2; -1 0 1 ];
    yfilter = [ 1 2 1; 0 0 0; -1 -2 -1 ];
    
    % Compute gradient magnitude and direction
    edgeX   = conv2(blurIMG, xfilter, 'same');
    edgeY   = conv2(blurIMG, yfilter, 'same');
    dirMap  = atan2d(edgeY,edgeX);
    gradMap = round(sqrt(edgeX.^2 + edgeY.^2));
    
    % Non Maximum Suppress Filter
    iiNegative = dirMap < 0;
    dirMap(iiNegative) = dirMap(iiNegative) + 180;
    
    for y = 2:h-1
        for x = 2:w-1
            % Find neighboring pixels to compare
            pix = gradMap(y,x);
            deg = dirMap(y,x);
            
            switch floor( (deg + 22.5)/45 )
                case {0,4} % 0 - 22.5
                           % 157.5 - 180
                    pix1 = gradMap(y,x+1);
                    pix2 = gradMap(y,x-1);
                case 1   % 22.5 - 67.5
                    pix1 = gradMap(y-1,x+1);
                    pix2 = gradMap(y+1,x-1);
                case 2   % 67.5 - 112.5
                    pix1 = gradMap(y-1,x);
                    pix2 = gradMap(y+1,x);
                case 3   % 112.5 - 157.5
                    pix1 = gradMap(y-1,x-1);
                    pix2 = gradMap(y+1,x+1);
            end
            
            % Suppress pixel if neighbors have a higher gradient
            if (pix < pix1 || pix < pix2)
                nonMaxFilter(y,x) = 0;
            elseif (pix == pix1 && pix == pix2)
                nonMaxFilter(y,x) = 0;
            else
                nonMaxFilter(y,x) = pix;
            end
        end
    end
    
    out = nonMaxFilter;
end

%% Hysteresis
function out = hysteresis (sobelIMG, threshold)
    % Remove noise by retaining only pixels adjacent to strong pixels
    lowMap  = double( (sobelIMG >= threshold(1)) & (sobelIMG < threshold(2)) );
    highMap = double( sobelIMG >= threshold(2) );
    yfilter = [ 1 1 1; 1 0 1; 1 1 1 ];
    adjacentEdges = conv2(highMap, yfilter, 'same');
    noiseMap = double(adjacentEdges==0);
    out = highMap + lowMap - noiseMap;
    out = double(out > 0);
end

%% Blob Detection
function out = blobDetection (edgesIMG)
    [h, w] = size(edgesIMG);
    visited = false(h,w);
    currentLabel = 1;
    out = {};
    
    for y = 1:h
        for x = 1:w
            if edgesIMG(y,x) == 0
                visited(y,x) = true;
            elseif visited(y,x)
            else
                edgeList = [x,y];
                stack = [y,x];
                while ~isempty(stack)
                    loc = stack(1,:);
                    stack(1,:) = [];
                    if visited(loc(1), loc(2)), continue; end
                    
                    % Mark location as true and mark this location to be
                    % its unique ID
                    visited(loc(1), loc(2)) = true;
                    %out(loc(1), loc(2)) = currentLabel;
                    
                    % Look at the 8 neighbouring locations
                    [locs_y, locs_x] = meshgrid(loc(1)-1:loc(1)+1, loc(2)-1:loc(2)+1);
                    locs_y = locs_y(:);
                    locs_x = locs_x(:);
                    
                    % Get rid of those locations out of bounds
                    out_of_bounds = locs_y < 1 | locs_y > h | locs_x < 1 | locs_x > w;
                    locs_y(out_of_bounds) = [];
                    locs_x(out_of_bounds) = [];
                    
                    % Get rid of those locations already visited
                    is_visited = visited(sub2ind([h w], locs_y, locs_x));
                    locs_y(is_visited) = [];
                    locs_x(is_visited) = [];
                    
                    % Get rid of those locations that are zero.
                    is_1 = edgesIMG(sub2ind([h w], locs_y, locs_x));
                    locs_y(~is_1) = [];
                    locs_x(~is_1) = [];
                    
                    % Add remaining locations to the stack
                    stack = [stack; [locs_y locs_x]];
                    edgeList = [edgeList; [locs_x locs_y]];
                end
                
                out{currentLabel} = edgeList;
                currentLabel = currentLabel +1;
            end
        end
    end
end

%% END