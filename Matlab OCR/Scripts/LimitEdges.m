%% Limit Number of Edges
function out = LimitEdges (combinedLIST)
    % Limit number of points per character
    DEFAULT = OcrDefaults;
    numberEdges = DEFAULT.numberEdges;
    limitEdgesLIST = zeros(size(combinedLIST, 2), numberEdges, 2);
    origNumEdges = sum(cellfun(@(c) size(c,1), combinedLIST), 1);
    limitRatio = origNumEdges / numberEdges;
    for k = 1:size(combinedLIST, 2)
        %iilimitEdges = round(1:limitRatio(k):origNumEdges(k));
        longEdgeLIST = combinedLIST{k};
        for e = 1:numberEdges
            %limitEdgesLIST(k, e, :) = longEdgeLIST(iilimitEdges(e), :);
            thisIndex = round(1+ limitRatio(k)*(e-1));
            if thisIndex > origNumEdges(k), thisIndex = origNumEdges(k); end
            limitEdgesLIST(k, e, :) = longEdgeLIST(thisIndex, :);
        end
    end
    %iilimitEdges = round(ones(size(combinedLIST, 2),1):limitRatio(:):origNumEdges(:));
    %limitEdgesLIST = combinedLIST(iilimitEdges);
    
    out = limitEdgesLIST;
end