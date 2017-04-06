%% Master
function ocr
    IMG      = LoadImage; if isempty(IMG), return, end
    EDGES    = CannyEdgeDetection(IMG);
    COMBINED = CombineCharacters(EDGES);
    LIMITED  = LimitEdges(COMBINED);
    TEXT     = ShapeRecognition(LIMITED);
    
    disp('Done.');
    disp(['Text: ' reshape(TEXT,1,[])]);
    %subplot(1,2,1), image( IMG ); axis image; title('Input');
    %subplot(1,2,2), image( gray2uint(EDGES*255) ); axis image; title('Output');
end