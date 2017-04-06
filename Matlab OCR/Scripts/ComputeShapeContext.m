%% Compute Shape Context
function out = ComputeShapeContext (EDGE)
    nPOINTS = size(EDGE,1);
    histogram = zeros( nPOINTS, 12, 5 );
    
    % Prepare lists of points
    X0 = repmat(EDGE(:,1)',nPOINTS,1);
    Y0 = repmat(EDGE(:,2)',nPOINTS,1);
    XY = repmat(EDGE,nPOINTS,1);
    
    % Calculate Euclidean distance and angle to each point
    dX = X0(:) - XY(:,1);
    dY = Y0(:) - XY(:,2);
    T  = atan2(dY, dX);
    R  = hypot(dX, dY);
    
    % Remove identical points and resize accordingly
    T(1 : nPOINTS+1 : nPOINTS*nPOINTS) = [];
    R(1 : nPOINTS+1 : nPOINTS*nPOINTS) = [];
    
    % Determine distance bin based on logarithmic distance
    R = R/mean(R);
    logR = log(R)./log(0.5);
    binR = -floor(logR) + 4;
    binR(binR < 1) = 1;
    binR(binR > 5) = 5;
    
    % Determine angle bin
    binTheta = ceil( (T+pi)*6/pi );
    
    % Create histograms
    binPoints = repmat(1:nPOINTS,nPOINTS-1,1);
    binList = [binPoints(:),binTheta,binR];
    histogram = accumarray(binList,1,size(histogram));
    
    out = histogram;
end