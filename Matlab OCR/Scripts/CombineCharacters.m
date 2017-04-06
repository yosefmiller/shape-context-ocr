%% Combine characters
function out = CombineCharacters (edgesLIST)
    % Find borders of each character
    numChars = size(edgesLIST,2);
    borderLIST = zeros(numChars, 2, 2);
    for k = 1:numChars
        borderLIST(k, 1, :) = min(edgesLIST{k});
        borderLIST(k, 2, :) = max(edgesLIST{k});
    end
    
    % Combine characters located within another
    combinedLIST = edgesLIST;
    iiExtraChars = [0];
    for k = 1:numChars
        for j = 1:numChars
            if k == j, continue;
            elseif any(iiExtraChars == k) || any(iiExtraChars == j), continue;
            elseif size(combinedLIST{j},1) <= 4
                combinedLIST{j} = [];
                iiExtraChars = [iiExtraChars; j];
            elseif all(borderLIST(j, 1, :) > borderLIST(k, 1, :)) && all(borderLIST(j, 2, :) < borderLIST(k, 2, :))
                combinedLIST{k} = [combinedLIST{k}; combinedLIST{j}];
                combinedLIST{j} = [];
                iiExtraChars = [iiExtraChars; j];
            end
        end
    end
    out = combinedLIST(~cellfun('isempty',combinedLIST));
end