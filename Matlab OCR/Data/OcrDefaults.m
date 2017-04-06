%% Default Configuration
function out = OcrDefaults
    out.ImageDir  = 'C:\Users\yosef\OneDrive\OCR Program\Handwritten';
    out.ImageFile = 'C:\Users\yosef\OneDrive\OCR Program\Handwritten\test1.png';
    out.TemplateFile = 'C:\Users\yosef\OneDrive\OCR Program\Mnist Dataset\train-images.idx3-ubyte';
    out.TrainedData = 'C:\Users\yosef\OneDrive\Matlab OCR\Data\TrainedData_handyosef.mat';
    out.gaussianSigma = 1.5;
    out.gaussianSize = 1;
    out.hysteresis = [100, 170];
    out.numberEdges = 100;
    out.transformCycles = 2;
    out.tpsRigidity = 10000;
    out.kNNsize = 3;
end