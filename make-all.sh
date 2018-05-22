#!/bin/bash

rm src/leveldb/libleveldb.a
rm src/leveldb/libmemenv.a
rm src/leveldb/port/port_win.o
rm src/leveldb/port/port_posix.o
rm src/leveldb/util/*.o
rm src/leveldb/table/*.o
rm src/leveldb/db/*.o
rm src/leveldb/helpers/memenv/*.o

make -f make-linux

rm src/leveldb/libleveldb.a
rm src/leveldb/libmemenv.a
rm src/leveldb/port/port_win.o
rm src/leveldb/port/port_posix.o
rm src/leveldb/util/*.o
rm src/leveldb/table/*.o
rm src/leveldb/db/*.o
rm src/leveldb/helpers/memenv/*.o

make -f make-win

rm src/leveldb/libleveldb.a
rm src/leveldb/libmemenv.a
rm src/leveldb/port/port_win.o
rm src/leveldb/port/port_posix.o
rm src/leveldb/util/*.o
rm src/leveldb/table/*.o
rm src/leveldb/db/*.o
rm src/leveldb/helpers/memenv/*.o

cd src
make -f makefile.unix
cd ..

cp src/elysiumd ../output/els/elysiumd
rm src/elysiumd

tar -czvf release/elysiumd-linux.tar.gz ../output/els/elysiumd
tar -czvf release/wallet-elysium-qt-linux.tar.gz ../output/els/elysium-qt
upx -9 ../output/els/elysium-qt.exe
zip release/wallet-elysium-qt-windows.zip ../output/els/elysium-qt.exe

rm src/leveldb/libleveldb.a
rm src/leveldb/libmemenv.a
rm src/leveldb/port/port_posix.o
rm src/leveldb/util/*.o
rm src/leveldb/table/*.o
rm src/leveldb/db/*.o
rm src/leveldb/helpers/memenv/*.o




