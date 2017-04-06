%% RECOGNITION STAGE
    % Steps for detecting character:
    % - generate initial shape context
    % - for each template:
    %     ~ compute cost matrix
    %     ~ calculate shape-context matching cost
    %     ~ [calculate additional appearance cost]
    %     ~ find best matching which minimizes total cost
    %     ~ apply transformations to align edge pairs
    %     ~ regenerate shape context
    %     ~ repeat previous steps 2-3 times
    %     ~ compute final total shape-context distance
    %     ~ select template with lowest cost
    %     ~ output to log

function out = ShapeRecognition (EDGES, varargin)
    % Initialize
    %profile on;
    DEFAULT = OcrDefaults;
    load(DEFAULT.TrainedData, 'TRAINED');
    if size(TRAINED,1) < 1, disp('No template data.'), out=''; return, end
    if size(TRAINED{1}.SHAPE, 1) ~= DEFAULT.numberEdges
        TemplateTraining('changenumber');
        load(DEFAULT.TrainedData, 'TRAINED');
    end
    CostMatrix = zeros( size(EDGES,1), size(TRAINED,2) );
    
    % Run algorithm for each input character
    progressbar(0);
    for c = 1:size(EDGES,1)
        % Run only for specific edge, if specified
        if nargin == 2 && isnumeric(varargin{1})
            if c ~= varargin{1}, continue; end
        end
        
        % Load initial edges
        for t = 1:size(TRAINED,2)
            % Load template shape context
            EDGE = squeeze( EDGES(c,:,:) );
            targetEDGE = squeeze( TRAINED{t}.LIMITED );
            targetHistLIST = TRAINED{t}.SHAPE;
            
            % Find correspondences, run transformations, and re-compute
            for cycle = 1:DEFAULT.transformCycles
                % Compute Shape Context descriptors for each point within
                % input character
                histogramLIST = ComputeShapeContext( EDGE );
                
                % Optimally associate matching points by minimizing the
                % difference between Shape Contexts. (The difference is
                % calculated using the Chi Squared Test Statistic.)
                shapeCostMatrix = findCostMatrix( histogramLIST, targetHistLIST );
                [assignments, shapeCost] = munkres(shapeCostMatrix); % mexLap lapjv_mat
                
                % Run transformations (eg. Affine) to align the two shapes.
                if (cycle == DEFAULT.transformCycles), continue; end
                [~, bendingCost] = applyTransformations(EDGE, targetEDGE, assignments);
            end
            
            % Store total matching cost
            CostMatrix(c,t) = shapeCost/size(EDGE,1) + bendingCost/size(EDGE,1)*500;
            progressbar(t/size(TRAINED,2));
        end
    end
    
    
    % Create list of prototype classes
    TrainedClasses = char(zeros(size(TRAINED,2), 1));
    for t = 1:size(TRAINED,2)
        TrainedClasses(t) = TRAINED{t}.VALUE;
    end
    
    % Compile results
    k = DEFAULT.kNNsize;
    [SortedCostMatrix, iiCostMatrix] = sort(CostMatrix, 2);
    iiLowestCost = iiCostMatrix(:, 1:k);
    OutputText = char(mode(double(TrainedClasses( iiLowestCost' ))))';
    
    % Store matching information for figure
    hFIG = findobj('Type','figure','Tag','OcrFigure');
    if ~isempty(hFIG)
        t = iiCostMatrix(varargin{1},1);
        D = guidata(hFIG);
        D.TEMPLATE.INDEX   = t;
        D.TEMPLATE.VALUE   = TRAINED{t}.VALUE;
        D.TEMPLATE.EDGES   = TRAINED{t}.EDGES;
        D.TEMPLATE.LIMITED = squeeze( TRAINED{t}.LIMITED );
        D.TEMPLATE.SHAPE   = TRAINED{t}.SHAPE;
        TestEDGES          = squeeze(D.LIMITED(varargin{1},:,:));
        TestSHAPES         = ComputeShapeContext( TestEDGES );
        shapeCostMatrix    = findCostMatrix( TestSHAPES, D.TEMPLATE.SHAPE );
        [assignments, ~]   = munkres(shapeCostMatrix);
        D.TEMPLATE.ALIGNED = D.TEMPLATE.LIMITED(assignments,:);
        D.TEMPLATE.TRANSF  = affineTransform3( TestEDGES, D.TEMPLATE.ALIGNED );
        %[warp,~,~,bendE]   = tpsGetWarp(DEFAULT.tpsRigidity, TestEDGES, D.TEMPLATE.ALIGNED);
        %[xsR,ysR]          = tpsInterpolate( warp, warp.xsS, warp.ysS, 0 );
        %D.TEMPLATE.TRANSF  = [xsR,ysR];
        D.RESULT.COST      = (SortedCostMatrix(varargin{1},:)/SortedCostMatrix(varargin{1},1)).^-1;
        D.RESULT.INDEX     = iiCostMatrix;
        D.RESULT.CHAR      = TrainedClasses( iiCostMatrix(varargin{1},:)' )';
        guidata(hFIG, D);
    end
    
    % Output result
    out = OutputText;
    progressbar(1);
    %profile viewer;
    %profile off;
end

%% Find Histogram Pairs
function out = findCostMatrix (histogramLIST, targetHistLIST)
    % Initialize cost matrix
    shapeCostMatrix = zeros( size(histogramLIST,1), size(targetHistLIST,1) );
    
    % Compare each point from input character to all template points.
    for ce = 1:size(histogramLIST,1)
        % Load histograms
        ch = reshape(histogramLIST(ce,:,:), size(histogramLIST,2), size(histogramLIST,3));
        chr = repmat(ch, 1,1, size(targetHistLIST,1));
        chr = permute(chr, [3 1 2]);
        
        % Compare histograms using the Chi Squared Test Statistic.
        % XSTS = summation of (g - h)^2 / (g + h) for each histogram bin
        D = chr+targetHistLIST;
        D(D==0) = 1;
        cost = (chr-targetHistLIST).^2./D;
        shapeCostMatrix(ce,:) = sum(sum(cost,2),3);
    end
    
    % Return the matrix representing costs of comparing the given shapes.
    out = shapeCostMatrix;
end

%% Apply Transformations
function [EDGE, COST] = applyTransformations (EDGE, targetEDGE, assignments)
    % Run the appropriate transformation
    targetEDGE = targetEDGE(assignments,:);
    [EDGE, COST] = affineTransform3(EDGE, targetEDGE);
    %DEFAULT = OcrDefaults;
    %[warp,~,~,COST] = tpsGetWarp(DEFAULT.tpsRigidity, EDGE, targetEDGE);
    %[xsR,ysR]        = tpsInterpolate( warp, warp.xsS, warp.ysS, 0 );
    %EDGE = [xsR,ysR];
end

%% Affine transformations
function [out, cost] = affineTransform (EDGE, targetEDGE)
    % Calculate offset
    o = targetEDGE - EDGE;
    o = round(sum(o)./length(EDGE));
    
    % Calculate scale
    %a = (EDGE*pinv(targetEDGE))';
    %EDGE = (a*EDGE')';
    
    % Apply transformation
    EDGE = EDGE + repmat(o,length(EDGE),1);
    out = EDGE;
    cost = 0;
end

%% Affine transformations
function [out, cost] = affineTransform2 (A, B)
    % Add column of ones acting as homogeneous coordinates
    A = [ones(size(A,1),1) A];
    B = [ones(size(B,1),1) B];
    
    % Calculate affine matrix
    %[R,t] = rigid_transform_3D(A, B);
    A = A + repmat(mean(B) - mean(A), length(A), 1);
    R = (pinv(A)*B)';
    t = -R*mean(A)' + mean(B)';
    
    % Calculate bending energy
    cost = sum(sum(R));
    
    % Apply transformation
    A = (R*A') + repmat(t, 1, length(A));
    out = A(2:3, :)';
end

%% Affine transformations
function [Popt, cost] = affineTransform3 (P, Q)
    % Calculate affine matrix
    offsetP = ones(size(P,1),1)*( mean(P) - mean(Q) );
    Po      = P - offsetP;
    A       = ((pinv(Q)*Po)')^-1;
    Popt    = (A*Po')';
    
    % Calculate cost
    cost = norm(A);
end

%% END
