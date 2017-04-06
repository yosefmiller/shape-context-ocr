#include "mex.h"
#include <iostream>
#include <cstdlib>
#include <ctime>

#include "munkres.h"

void mexFunction( int nlhs, mxArray *plhs[], 
		  int nrhs, const mxArray*prhs[] )
     
{ 
    int m,n,row,col; 
    int rowcount,colcount;
    double *inputcost,*sol;
    Munkres munk;
    Matrix<double> matrix;
    mxArray *mxSol;
    
    /* Check for proper number of arguments */
    if (nrhs != 1) { 
        mexErrMsgTxt("One input arguments required."); 
    } else if (nlhs > 1) {
        mexErrMsgTxt("Only one output arguments allowed."); 
    } 
    
    /* Check the dimensions of Y.  Y can be 4 X 1 or 1 X 4. */ 
    
    m = mxGetM(prhs[0]); 
    n = mxGetN(prhs[0]);
   
    /*
     if (!mxIsDouble(prhs[0]) 	|| m != n ) { 
        mexErrMsgTxt("The only input must be a square double matrix."); 
    }
     */
    
    inputcost = (double *) mxGetData(prhs[0]);
    
    matrix.resize(m, n);
    
    /* Initialize matrix */
    
	for (row = 0 ; row < m ; row++ ) {
		for ( int col = 0 ; col < n ; col++ ) {
			matrix(row,col) = inputcost[col*m+row];            
		}
	}
    /* Apply Munkres algorithm to matrix*/
	munk.solve(matrix);    
    /* initialize the c funcition input*/
    
    /* Display solved matrix.
    
	for (row = 0 ; row < m ; row++ ) {
		for (col = 0 ; col < n ; col++ ) {
			std::cout.width(2);
			std::cout << matrix(row,col) << ",";
		}
		std::cout << std::endl;
	}
    */
    
    mxSol = mxCreateNumericMatrix(1, n, mxDOUBLE_CLASS, mxREAL);
    sol = (double*) mxGetData(mxSol);
    
    /*
    for (row = 0 ; row < m ; row++ ) {
		rowcount = 0;
		for ( col = 0 ; col < n ; col++  ) {
			if ( matrix(row,col) == 0 ){
                sol[row]=col+1;
				rowcount++;
            }
		}
		if ( rowcount != 1 )
			std::cerr << "Row " << row << " has " << rowcount << " columns that have been matched." << std::endl;
	}
    */
    
	for (  col = 0 ; col < n ; col++ ) {
		colcount = 0;
		for (row = 0 ; row < m ; row++ ) {
			if ( matrix(row,col) == 0 ){
                /* std::cout<<row+1<<"\t"; */
                sol[col]=row+1;
				colcount++;
            }
		}
		/*if ( colcount != 1 )
			std::cerr << "Column " << col << " has " << colcount << " rows that have been matched." << std::endl;
         */
	}
    plhs[0] = mxSol;
    return;
}
