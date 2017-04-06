%% Load Image
function out = LoadImage(varargin)
    out = [];
    DEFAULT = OcrDefaults;
    if (exist(DEFAULT.ImageFile,'file') && nargin == 0)
        setImagePath(DEFAULT.ImageFile);
        out = readImageFromFile(DEFAULT.ImageFile);
    elseif (exist(DEFAULT.TemplateFile,'file') && nargin == 1 && strcmp(varargin{1}, 'Template'))
        out = readImageFromFile(DEFAULT.TemplateFile);
    else
        cwd = pwd;
        cd(DEFAULT.ImageDir);
        [file,path] = uigetfile({'*.jpg;*.png;*.gif','All Image Files';...
            '*.*','All Files' },'Load Image',DEFAULT.ImageDir);
        cd(cwd);
        if isnumeric(file), return, end
        setImagePath(file);
        
        out = readImageFromFile(fullfile(path,file));
    end
end

%% Update image path if running ocr figure
function setImagePath (file)
    fig = findobj('tag', 'OcrFigure');
    if isempty(fig), return; end
    hPATH = findobj(fig, 'tag', 'image_path');
    if isempty(hPATH), return; end
    filename = strsplit(file, '\\');
    filename = filename{end};
    set(hPATH, 'String', filename);
end

%% Check if image is a mnist database image
function out = readImageFromFile (file)
    [~,~,ext] = fileparts(file);
    if strcmp(ext,'.idx3-ubyte')
        out = loadMNISTImages(file);
    else
        out = imread(file);
    end
end