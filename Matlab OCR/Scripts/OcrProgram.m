%% Optical Character Recognition algorithm 
% using the Shape Context descriptor
function OcrProgram(varargin)
    % Direct callbacks to appropriate functions
    if nargin==0
        % Initialize
        delete(findobj('tag','OcrFigure'))
        hFIG = openfig('OcrProgram.fig');
        InitImage(hFIG, false);
    elseif nargin==1 && strcmp(varargin{1}, 'callbacks')
        % Handle callbacks
        action = get(gcbo,'tag');
        callbacks(action);
    end
end

%% Custom Callback function
function callbacks(varargin)
    action = varargin{1};
    switch action
        case 'browse_button'
            InitImage(gcbf, true);
        case 'run_button'
            BeginRecognition(gcbf);
            NextCharacter(gcbf);
            ProcessCharacter(gcbf);
        case 'next_character'
            NextCharacter(gcbf);
            ProcessCharacter(gcbf);
        case 'repeat_button'
            ProcessCharacter(gcbf);
        case 'skip_button'
            NextCharacter(gcbf);
        case 'change_view'
            ChangeView(gcbf);
    end
end

%% Initialize
function InitImage (hFIG, isBrowse)
    % Load image
    if isBrowse, IMG = LoadImage(true);
    else IMG = LoadImage; end
    if isempty(IMG), return, end
    
    % Store image data
    D.IMG = IMG;
    guidata(hFIG, D);
    
    % Display image
    handles = guihandles(hFIG);
    hVIEW   = handles.change_view;
    set(hVIEW, 'Value', 1);
    ChangeView(hFIG);
end

%% Begin recognition
function BeginRecognition (hFIG)
    D = guidata(hFIG);
    D.CANNY    = CannyEdgeDetection(D.IMG);
    D.COMBINED = CombineCharacters(D.CANNY.edgesLIST);
    D.LIMITED  = LimitEdges(D.COMBINED);
    D.INDEX = 0;
    guidata(hFIG, D);
end

%% Procceed to next character
function NextCharacter(hFIG)
    % Increment index
    D = guidata(hFIG);
    D.INDEX = D.INDEX + 1;
    if D.INDEX > length(D.COMBINED), return, end
    guidata(hFIG, D);
    
    % Retrieve GUI handles
    handles = guihandles(hFIG);
    hPPANEL = handles.results_panel;
    hTEXT   = handles.char_text;
    hVIEW   = handles.change_view;
    
    % Display points
    set(hPPANEL, 'Visible', 'on');
    set(hTEXT, 'String', '');
    set(hVIEW, 'Value', 4);
    ChangeView(hFIG);
end

%% Process character
function ProcessCharacter(hFIG)
    % Detect character
    D = guidata(hFIG);
    TEXT = ShapeRecognition(D.LIMITED, D.INDEX);
    TEXT = TEXT(D.INDEX);
    
    % Retrieve GUI handles
    handles = guihandles(hFIG);
    hDPANEL = handles.description_panel;
    hTEXT   = handles.char_text;
    hVIEW   = handles.change_view;
    
    % Display result
    set(hDPANEL, 'Visible', 'on');
    set(hTEXT, 'String', TEXT);
    set(hVIEW, 'Value', 11);
    ChangeView(hFIG);
end

%%
function NextCharacter2 (hFIG)
    % Load and clear plot
    handles = guihandles(hFIG);
    hPLOT   = handles.char_plot;
    hVALUE  = handles.train_value;
    axes(hPLOT);
    hold on;
    cla;
    
    % Graph next character
    T = guidata(hFIG);
    T.INDEX = T.INDEX + 1;
    if T.INDEX > length(T.EDGES), return, end
    POINTS = T.EDGES{T.INDEX};
    scatter(POINTS(:,1), POINTS(:,2),'.');
    grid off;
    axis ij;
    guidata(hFIG, T);
    set(hFIG,'Visible','on');
    set(hPLOT,'Visible','on');
    set(hVALUE, 'String', '');
end

%% Change View
function ChangeView (hFIG)
    D = guidata(hFIG);
    
    % Retrieve GUI handles
    handles = guihandles(hFIG);
    hPLOT  = handles.image_plot;
    hVIEW  = handles.change_view;
    hDESCR = handles.description_text;
    view_list = get(hVIEW, 'String');
    view_num  = get(hVIEW, 'Value');
    view_current = view_list{view_num};
    axes(hPLOT); hold on; cla; colormap('default');
    set(hDESCR, 'String', '');
    set(gca,'pos',[4.5 1.1 73 28.5]); title('');
    
    switch view_current
        case ' Original Image'
            image(D.IMG);
            grid off; axis ij image off; zoom off;
        case ' Sobel Image'
            image(D.CANNY.sobelIMG);
            grid off; axis ij equal off; zoom reset off;
            set(hDESCR, 'String', 'A visual representation of the Sobel Edge Gradient, used in determining each character''s contours as part of Canny Edge Detection.');
        case ' All Edges'
            image(gray2uint(-D.CANNY.edgesIMG+1)*255);
            grid off; axis ij equal off; zoom reset off;
            set(hDESCR, 'String', 'All contours within the image detected by the Canny Edge Detector.');
        case ' Test Image'
            EdgeList = D.COMBINED{D.INDEX};
            image(repmat(uint8(-255.*D.CANNY.grayIMG+255),[1 1 3]));
            scatter(EdgeList(:,1)', EdgeList(:,2)','b.');
            grid off; axis ij image off; zoom off;
            axis([min(EdgeList(:,1)) max(EdgeList(:,1)) min(EdgeList(:,2)) max(EdgeList(:,2))]);
        case ' Test Edges'
            EdgeList = D.COMBINED{D.INDEX};
            scatter(EdgeList(:,1)', EdgeList(:,2)','b.');
            grid off; axis ij image off; zoom off;
            set(hDESCR, 'String', 'All contours of the detected character currently being processed by the OCR program.');
        case ' Test Limited Edges'
            scatter(D.LIMITED(D.INDEX,:,1)', D.LIMITED(D.INDEX,:,2)','b.');
            scatter(D.LIMITED(D.INDEX,1,1), D.LIMITED(D.INDEX,1,2),'mo');
            grid off; axis ij image off; zoom off;
            set(hDESCR, 'String', 'A select number of contours such that the program is neither too discrimitory nor general as a descriptor.');
        case ' Test Shape Context'
            set(gca,'pos',[9 7 69 15]);
            scLIST = ComputeShapeContext( squeeze( D.LIMITED(D.INDEX,:,:)) );
            sc = flip(shiftdim(scLIST(1,:,:))');
            image(sc+1); colormap(flipud(gray));
            xlabel('\theta angle bin'); ylabel('log(r) bin');
            grid off; axis ij image on; zoom off;
        case ' Template Edges'
            scatter(D.TEMPLATE.EDGES(:,1)', D.TEMPLATE.EDGES(:,2)','r.');
            grid off; axis ij image off; zoom off;
        case ' Template Limited Edges'
            scatter(D.TEMPLATE.LIMITED(:,1)', D.TEMPLATE.LIMITED(:,2)','r.');
            scatter(D.TEMPLATE.LIMITED(1,1), D.TEMPLATE.LIMITED(1,2),'mo');
            grid off; axis ij image off; zoom off;
        case ' Template Paired Shape Context'
            set(gca,'pos',[9 7 69 15]);
            scLIST = D.TEMPLATE.SHAPE;
            sc = flip(shiftdim(scLIST(1,:,:))');
            image(sc+1); colormap(flipud(gray));
            xlabel('\theta angle bin'); ylabel('log(r) bin');
            grid off; axis ij image on; zoom off;
        case ' Test Transformed Edges'
            scatter(D.TEMPLATE.TRANSF(:,1), D.TEMPLATE.TRANSF(:,2),'b.');
            grid off; axis ij image off; zoom off;
        case ' Alignment after Transformation'
            scatter(D.TEMPLATE.TRANSF(:,1), D.TEMPLATE.TRANSF(:,2),'b.');
            scatter(D.TEMPLATE.ALIGNED(:,1), D.TEMPLATE.ALIGNED(:,2),'r.');
            for k=1:length(D.TEMPLATE.TRANSF), plot([D.TEMPLATE.TRANSF(k,1),D.TEMPLATE.ALIGNED(k,1)],[D.TEMPLATE.TRANSF(k,2),D.TEMPLATE.ALIGNED(k,2)],'k-'); end
            grid off; axis ij image off; zoom off;
            set(hDESCR, 'String', 'A visual representation of the rough alignment and correspondences determined by the Hungarian Method in conjunction with an Affine Transformation.');
        case ' Confidence in results'
            set(gca,'pos',[12 3.5 67 25]);
            axis([0 length(D.RESULT.COST) D.RESULT.COST(end) 1]);
            %plot( D.RESULT.COST, 'b.');
            plot( bsxfun(@times, D.RESULT.COST, double(D.RESULT.CHAR ~= D.TEMPLATE.VALUE)), 'b.');
            plot( bsxfun(@times, D.RESULT.COST, double(D.RESULT.CHAR == D.TEMPLATE.VALUE)), 'g.');
            xlabel('\it Rank')
            ylabel('\it Likelihood')
            title('\bf Match Confidence')
            grid on; axis xy normal on; zoom on;
    end
end